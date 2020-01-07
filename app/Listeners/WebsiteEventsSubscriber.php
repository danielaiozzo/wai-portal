<?php

namespace App\Listeners;

use App\Enums\Logs\EventType;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteType;
use App\Events\Website\PrimaryWebsiteNotTracking;
use App\Events\Website\WebsiteActivated;
use App\Events\Website\WebsiteAdded;
use App\Events\Website\WebsiteArchived;
use App\Events\Website\WebsiteArchiving;
use App\Events\Website\WebsiteDeleted;
use App\Events\Website\WebsitePurged;
use App\Events\Website\WebsitePurging;
use App\Events\Website\WebsiteRestored;
use App\Events\Website\WebsiteStatusChanged;
use App\Events\Website\WebsiteUnarchived;
use App\Events\Website\WebsiteUpdated;
use App\Events\Website\WebsiteUrlChanged;
use App\Exceptions\AnalyticsServiceException;
use App\Exceptions\CommandErrorException;
use App\Models\Website;
use App\Traits\InteractsWithRedisIndex;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;

/**
 * Websites related events subscriber.
 */
class WebsiteEventsSubscriber implements ShouldQueue
{
    use InteractsWithRedisIndex;

    /**
     * Website activated event callback.
     *
     * @param WebsiteAdded $event the event
     */
    public function onAdded(WebsiteAdded $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendWebsiteActivatedNotification($website);
//        }
//
//        //Notify Public Administration
//        $publicAdministration->sendWebsiteActivatedNotification($website);

        //Update Redisearch websites index
        $this->updateWebsitesIndex($website);

        logger()->notice(
            'Website ' . $website->info . ' added of type ' . $website->type->description,
            [
                'event' => EventType::WEBSITE_ADDED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website activated event callback.
     *
     * @param WebsiteActivated $event the event
     */
    public function onActivated(WebsiteActivated $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendWebsiteActivatedNotification($website);
//        }

        logger()->notice(
            'Website ' . $website->info . ' activated',
            [
                'event' => EventType::WEBSITE_ACTIVATED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );

        try {
            $this->updatePublicDashboardUser($website);
        } catch (Exception $exception) {
            report($exception);
        }

        //NOTE: primary websites are added to roll up report
        //      by "public administration activated" event handler
        if (!$website->type->is(WebsiteType::PRIMARY)) {
            try {
                $website->publicAdministration->addToRollUp($website);
            } catch (Exception $exception) {
                report($exception);
            }
        }
    }

    /**
     * Website updated event callback.
     *
     * @param WebsiteUpdated $event the event
     */
    public function onUpdated(WebsiteUpdated $event): void
    {
        //Update Redisearch websites index
        $this->updateWebsitesIndex($event->getWebsite());
    }

    /**
     * Handle website status changed event.
     *
     * @param WebsiteStatusChanged $event the event
     */
    public function onWebsiteStatusChanged(WebsiteStatusChanged $event): void
    {
        Cache::forget(Website::WEBSITE_COUNT_KEY);
        $website = $event->getWebsite();
        logger()->notice(
            'Website ' . $website->info . ' status changed from "' . $event->getOldStatus()->description . '" to "' . $website->status->description . '"',
            [
                'event' => EventType::WEBSITE_STATUS_CHANGED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Handle website URL changed event.
     *
     * @param WebsiteUrlChanged $event the event
     */
    public function onWebsiteUrlChanged(WebsiteUrlChanged $event): void
    {
        $website = $event->getWebsite();
        logger()->notice(
            'Website' . $website->info . ' URL updated from ' . e($event->getOldUrl()) . ' to ' . e($website->url),
            [
                'event' => EventType::WEBSITE_URL_CHANGED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website archiving event callback.
     *
     * @param WebsiteArchiving $event the event
     */
    public function onArchiving(WebsiteArchiving $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        //Notify website administrators
//        $users = $website->getAdministrators($website);
//        foreach ($users as $user) {
//            $user->sendWebsiteArchivingNotification($website, $event->getWebsite());
//        }

        logger()->notice(
            'Website ' . $website->info . ' reported as not active and scheduled for archiving',
            [
                'event' => EventType::WEBSITE_ARCHIVING,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website archived event callback.
     *
     * @param WebsiteArchived $event the event
     */
    public function onArchived(WebsiteArchived $event): void
    {
        $website = $event->getWebsite();
        $manual = $event->isManual();
        $reason = $manual ? 'manually' : 'due to inactivity';

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        //Notify website administrators
//        $users = $website->getAdministrators($website,);
//        foreach ($users as $user) {
//            $user->sendWebsiteArchivedNotification($website, $manual);
//        }

        logger()->notice(
            'Website ' . $website->info . ' archived ' . $reason,
            [
                'event' => EventType::WEBSITE_ARCHIVED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website unarchived event callback.
     *
     * @param WebsiteUnarchived $event the event
     */
    public function onUnarchived(WebsiteUnarchived $event): void
    {
        $website = $event->getWebsite();
        //TODO: notificare qualcuno? è un'azione solo manuale
        logger()->notice(
            'Website ' . $website->info . ' unarchived manually',
            [
                'event' => EventType::WEBSITE_UNARCHIVED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website near-to-be-purged event callback.
     *
     * @param WebsitePurging $event the event
     */
    public function onPurging(WebsitePurging $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendWebsitePurgingNotification($website);
//        }

        logger()->notice(
            'Website ' . $website->info . ' scheduled purging',
            [
                'event' => EventType::WEBSITE_PURGING,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website purged event callback.
     *
     * @param WebsitePurged $event the event
     */
    public function onPurged(WebsitePurged $event): void
    {
        Cache::forget(Website::WEBSITE_COUNT_KEY);
        $website = json_decode($event->getWebsiteJson());
        $websiteInfo = '"' . e($website->name) . '" [' . $website->slug . ']';
        //NOTE: toJson: relationship attributes are snake_case
        logger()->notice(
            'Website ' . $websiteInfo . ' purged',
            [
                'event' => EventType::WEBSITE_PURGED,
                'website' => $website->id,
                'pa' => $website->public_administration->ipa_code,
            ]
        );
    }

    /**
     * Website deleted event callback.
     *
     * @param WebsiteDeleted $event the event
     */
    public function onDeleted(WebsiteDeleted $event): void
    {
        $website = $event->getWebsite();
        logger()->notice(
            'Website ' . $website->info . ' deleted.',
            [
                'event' => EventType::WEBSITE_DELETED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Website restored event callback.
     *
     * @param WebsiteRestored $event the event
     */
    public function onRestored(WebsiteRestored $event): void
    {
        $website = $event->getWebsite();
        logger()->notice(
            'Website ' . $website->info . ' restored.',
            [
                'event' => EventType::WEBSITE_RESTORED,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Primary website not tracking event callback.
     *
     * @param PrimaryWebsiteNotTracking $event the event
     */
    public function onPrimaryWebsiteNotTracking(PrimaryWebsiteNotTracking $event): void
    {
        $website = $event->getWebsite();

        //TODO: da testare e verificare per attività "Invio mail e PEC"
//        $publicAdministration = $website->publicAdministration;
//        //Notify Website administrators
//        $users = $publicAdministration->getAdministrators();
//        foreach ($users as $user) {
//            $user->sendPrimaryWebsiteNotTrackingNotification();
//        }

        logger()->notice(
            'Primary website ' . $website->info . ' tracking inactive.',
            [
                'event' => EventType::PRIMARY_WEBSITE_NOT_TRACKING,
                'website' => $website->id,
                'pa' => $website->publicAdministration->ipa_code,
            ]
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param Dispatcher $events the dispatcher
     */
    public function subscribe($events): void
    {
        $events->listen(
            'App\Events\Website\WebsiteAdded',
            'App\Listeners\WebsiteEventsSubscriber@onAdded'
        );

        $events->listen(
            'App\Events\Website\WebsiteActivated',
            'App\Listeners\WebsiteEventsSubscriber@onActivated'
        );

        $events->listen(
            'App\Events\Website\WebsiteUpdated',
            'App\Listeners\WebsiteEventsSubscriber@onUpdated'
        );

        $events->listen(
            'App\Events\Website\WebsiteStatusChanged',
            'App\Listeners\WebsiteEventsSubscriber@onWebsiteStatusChanged'
        );

        $events->listen(
            'App\Events\Website\WebsiteUrlChanged',
            'App\Listeners\WebsiteEventsSubscriber@onWebsiteUrlChanged'
        );

        $events->listen(
            'App\Events\Website\WebsiteArchiving',
            'App\Listeners\WebsiteEventsSubscriber@onArchiving'
        );

        $events->listen(
            'App\Events\Website\WebsiteArchived',
            'App\Listeners\WebsiteEventsSubscriber@onArchived'
        );

        $events->listen(
            'App\Events\Website\WebsiteUnarchived',
            'App\Listeners\WebsiteEventsSubscriber@onUnarchived'
        );

        $events->listen(
            'App\Events\Website\WebsitePurging',
            'App\Listeners\WebsiteEventsSubscriber@onPurging'
        );

        $events->listen(
            'App\Events\Website\WebsitePurged',
            'App\Listeners\WebsiteEventsSubscriber@onPurged'
        );

        $events->listen(
            'App\Events\Website\WebsiteDeleted',
            'App\Listeners\WebsiteEventsSubscriber@onDeleted'
        );

        $events->listen(
            'App\Events\Website\WebsiteRestored',
            'App\Listeners\WebsiteEventsSubscriber@onRestored'
        );

        $events->listen(
            'App\Events\Website\PrimaryWebsiteNotTracking',
            'App\Listeners\WebsiteEventsSubscriber@onPrimaryWebsiteNotTracking'
        );
    }

    /**
     * Grant permission to public dashboard user on a website.
     *
     * @param Website $website the new website
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     */
    private function updatePublicDashboardUser(Website $website): void
    {
        app()->make('analytics-service')->setWebsiteAccess(config('analytics-service.viewer_login'), WebsiteAccessType::VIEW, $website->analytics_id);
    }
}
