<?php

namespace App\Jobs;

use App\Enums\PublicAdministrationStatus;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Events\Jobs\PendingWebsitesCheckCompleted;
use App\Events\PublicAdministration\PublicAdministrationActivated;
use App\Events\PublicAdministration\PublicAdministrationActivationFailed;
use App\Events\PublicAdministration\PublicAdministrationPurged;
use App\Events\User\UserActivated;
use App\Events\User\UserActivationFailed;
use App\Events\User\UserWebsiteAccessChanged;
use App\Events\User\UserWebsiteAccessFailed;
use App\Events\Website\WebsiteActivated;
use App\Events\Website\WebsitePurged;
use App\Events\Website\WebsitePurging;
use App\Exceptions\AnalyticsServiceException;
use App\Exceptions\CommandErrorException;
use App\Models\Website;
use BenSampo\Enum\Exceptions\InvalidEnumMemberException;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;

class ProcessPendingWebsites implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tokenAuth;

    public function __construct()
    {
        $this->tokenAuth = config('analytics-service.admin_token');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $pendingWebsites = Website::where('status', WebsiteStatus::PENDING)->get();

        $websites = $pendingWebsites->mapToGroups(function ($website) {
            try {
                $analyticsService = app()->make('analytics-service');
                if ($analyticsService->getLiveVisits($website->analytics_id, 60, $this->tokenAuth) > 0 || $analyticsService->getSiteTotalVisits($website->analytics_id, $website->created_at->format('Y-m-d'), $this->tokenAuth) > 0) {
                    $publicAdministration = $website->publicAdministration;

                    $website->status = WebsiteStatus::ACTIVE;
                    if (!$website->save()) {
                        return [
                            'failed' => [
                                'website' => $website->slug,
                                'reason' => 'Unable to persist state into database',
                            ],
                        ];
                    }

                    if ($publicAdministration->status->is(PublicAdministrationStatus::PENDING)) {
                        $publicAdministration->status = PublicAdministrationStatus::ACTIVE;
                        if (!$publicAdministration->save()) {
                            event(new PublicAdministrationActivationFailed($publicAdministration, 'Unable to update state into database'));

                            return [
                                'failed' => [
                                    'website' => $website->slug,
                                    'reason' => 'Unable to activate public administration',
                                ],
                            ];
                        }

                        $pendingUser = $publicAdministration->users()->where('status', UserStatus::PENDING)->first();
                        if ($pendingUser) {
                            $pendingUser->partial_analytics_password = Str::random(rand(32, 48));
                            $pendingUser->status = UserStatus::ACTIVE;
                            if (!$pendingUser->save()) {
                                event(new UserActivationFailed($pendingUser, 'Unable to update state into database or Analytics Service'));

                                return [
                                    'failed' => [
                                        'website' => $website->slug,
                                        'reason' => 'Unable to activate admin user',
                                    ],
                                ];
                            }

                            $pendingUser->roles()->detach();
                            Bouncer::scope()->to($publicAdministration->id);
                            $pendingUser->assign('admin');

                            event(new UserActivated($pendingUser));
                        }

                        event(new PublicAdministrationActivated($publicAdministration));
                    }

                    foreach ($publicAdministration->users as $user) {
                        try {
                            //TODO: da modificare com l'implementazione dei permessi per sito
                            if ($user->isAn('admin')) {
                                $access = WebsiteAccessType::ADMIN;
                            } elseif ($user->isA('manager')) {
                                $access = WebsiteAccessType::WRITE;
                            } elseif ($user->isA('reader')) {
                                $access = WebsiteAccessType::VIEW;
                            } else {
                                $access = WebsiteAccessType::NO_ACCESS;
                            }
                            $analyticsService->setWebsitesAccess($user->uuid, $access, $website->analytics_id, $this->tokenAuth);

                            event(new UserWebsiteAccessChanged($user, $website, new WebsiteAccessType($access)));
                        } catch (AnalyticsServiceException | InvalidEnumMemberException | CommandErrorException $exception) {
                            event(new UserWebsiteAccessFailed($user, $website, $exception->getMessage()));
                        }
                    }
                    event(new WebsiteActivated($website));

                    return [
                        'activated' => [
                            'website' => $website->slug,
                        ],
                    ];
                }

                if (10 === $website->created_at->diffInDays(Carbon::now())) {
                    event(new WebsitePurging($website));

                    return [
                        'purging' => [
                            'website' => $website->slug,
                        ],
                    ];
                }

                if ($website->created_at->diffInDays(Carbon::now()) > 15) {
                    $publicAdministration = $website->publicAdministration;

                    if ($publicAdministration->status->is(PublicAdministrationStatus::PENDING)) {
                        $pendingUser = $publicAdministration->users()->where('status', UserStatus::PENDING)->first();
                        if (null !== $pendingUser) {
                            $pendingUser->publicAdministrations()->detach($publicAdministration->id);
                            if (!$pendingUser->save() || !$publicAdministration->forceDelete()) {
                                return [
                                    'failed' => [
                                        'website' => $website->slug,
                                        'reason' => 'Unable to purge related public administration',
                                    ],
                                ];
                            }
                        }
                        event(new PublicAdministrationPurged($publicAdministration->toJson()));
                    } elseif (!$website->forceDelete()) {
                        return [
                            'failed' => [
                                'website' => $website->slug,
                                'reason' => 'Unable to purge from database',
                            ],
                        ];
                    }

                    $analyticsService->deleteSite($website->analytics_id, $this->tokenAuth);

                    event(new WebsitePurged($website->toJson()));

                    return [
                        'purged' => [
                            'website' => $website->slug,
                        ],
                    ];
                }
            } catch (BindingResolutionException $exception) {
                report($exception);

                return [
                    'failed' => [
                        'website' => $website->slug,
                        'reason' => 'Unable to bind to Analytics Service',
                    ],
                ];
            } catch (AnalyticsServiceException $exception) {
                report($exception);

                return [
                    'failed' => [
                        'website' => $website->slug,
                        'reason' => 'Unable to contact the Analytics Service',
                    ],
                ];
            } catch (CommandErrorException $exception) {
                report($exception);

                return [
                    'failed' => [
                        'website' => $website->slug,
                        'reason' => 'Invalid command for Analytics Service',
                    ],
                ];
            }

            return [
                'ignored' => [
                    'website' => $website->slug,
                ],
            ];
        });

        event(new PendingWebsitesCheckCompleted(
            empty($websites->get('activated')) ? [] : $websites->get('activated')->all(),
            empty($websites->get('purging')) ? [] : $websites->get('purging')->all(),
            empty($websites->get('purged')) ? [] : $websites->get('purged')->all(),
            empty($websites->get('failed')) ? [] : $websites->get('failed')->all()
        ));
    }
}
