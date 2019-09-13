<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\User\UserInvited;
use App\Exceptions\OperationNotAllowedException;
use App\Models\User;
use App\Traits\SendsResponse;
use App\Transformers\SuperAdminUserTransformer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use Yajra\DataTables\DataTables;

/**
 * Super admin users management controller.
 */
class SuperAdminUserController extends Controller
{
    use SendsResponse;

    /**
     * Display super admin user list.
     *
     * @return View the view
     */
    public function index(): View
    {
        $superUsersDatatable = [
            'datatableOptions' => [
                'searching' => true,
                'columnFilters' => [
                    'status' => [
                        'filterLabel' => __('stato'),
                    ],
                ],
            ],
            'columns' => [
                ['data' => 'name', 'name' => 'nome e cognome'],
                ['data' => 'email', 'name' => 'email'],
                ['data' => 'added_at', 'name' => 'iscritto dal'],
                ['data' => 'status', 'name' => 'stato'],
                ['data' => 'icons', 'name' => '', 'orderable' => false],
                ['data' => 'buttons', 'name' => '', 'orderable' => false],
            ],
            'source' => route('admin.users.data.json'),
            'caption' => __('elenco degli utenti super amministratori presenti su Web Analytics Italia'),
            'columnsOrder' => [['added_at', 'asc'], ['name', 'asc']],
        ];

        return view('pages.admin.users.index')->with($superUsersDatatable);
    }

    /**
     * Show the form for creating a new user.
     *
     * @return View the view
     */
    public function create(): View
    {
        return view('pages.admin.users.add');
    }

    /**
     * Create a new user.
     *
     * @param Request $request the incoming request
     *
     * @throws \Exception if unable to generate user UUID
     *
     * @return RedirectResponse the server redirect response
     */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->all();
        $validator = validator($input, [
            'name' => 'required',
            'family_name' => 'required',
            'email' => 'required|email',
        ])->after(function ($validator) use ($input) {
            if (array_key_exists('email', $input) && User::where('email', $input['email'])
                ->whereIs(UserRole::SUPER_ADMIN)->get()->isNotEmpty()) {
                $validator->errors()->add('email', __('validation.unique', ['attribute' => __('validation.attributes.email')]));
            }
        })->validate();

        $temporaryPassword = Str::random(16);

        $user = User::create([
            'name' => $input['name'],
            'family_name' => $input['family_name'],
            'email' => $input['email'],
            'uuid' => Uuid::uuid4()->toString(),
            'password' => Hash::make($temporaryPassword),
            'password_changed_at' => Carbon::now()->subDays(1 + config('auth.password_expiry')),
            'status' => UserStatus::INVITED,
        ]);

        $user->assign(UserRole::SUPER_ADMIN);

        if (!empty($user->passwordResetToken)) {
            $user->passwordResetToken->delete();
        }

        event(new UserInvited($user, $request->user()));

        return redirect()->route('admin.users.index')
            ->withModal([
                'title' => __('Nuovo utente super amministratore creato'),
                'icon' => 'it-check-circle',
                'message' => implode("\n", [
                    __(':user è stato aggiunto come amministratore di Web Analytics Italia.', ['user' => e($user->full_name)]),
                    __('Comunica al nuovo utente la sua password temporanea <code>:password</code> usando un canale diverso dalla mail :email.', ['password' => $temporaryPassword, 'email' => $input['email']]),
                    __('<strong>Attenzione! Questa password non sarà mai più visualizzata.</strong>'),
                ]),
                'image' => 'https://placeholder.pics/svg/180',
            ]);
    }

    /**
     * Show the user details page.
     *
     * @param User $user the user to display
     *
     * @return View the view
     */
    public function show(User $user): View
    {
        return view('pages.admin.users.show')->with(['user' => $user]);
    }

    /**
     * Show the form to edit an existing user.
     *
     * @param User $user the user to edit
     *
     * @return \Illuminate\View\View the view
     */
    public function edit(User $user): View
    {
        return view('pages.admin.users.edit')->with(['user' => $user]);
    }

    /**
     * Update the user information.
     *
     * @param Request $request the incoming request
     * @param User $user the user to update
     *
     * @return RedirectResponse the server redirect response
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $input = $request->all();
        $validator = validator($input, [
            'name' => 'required',
            'family_name' => 'required',
            'email' => 'required|email',
        ])->after(function ($validator) use ($input, $user) {
            if (array_key_exists('email', $input) && User::where('email', $input['email'])
                ->where('id', '<>', $user->id)->whereIs(UserRole::SUPER_ADMIN)->get()->isNotEmpty()) {
                $validator->errors()->add('email', __('validation.unique', ['attribute' => __('validation.attributes.email')]));
            }
        })->validate();

        $user->fill([
            'name' => $input['name'],
            'family_name' => $input['family_name'],
            'email' => $input['email'],
        ]);
        $user->save();

        return redirect()->route('admin.users.index')->withAlert(['success' => "L'utente amministratore " . $user->info . ' è stato modificato.']); //TODO: put message in lang file
    }

    /**
     * Suspend an existing user.
     *
     * @param Request $request the incoming request
     * @param User $user the user to suspend
     *
     * @return \Illuminate\Http\JsonResponse the JSON response
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        if ($user->status->is(UserStatus::SUSPENDED)) {
            return $this->notModifiedResponse();
        }

        try {
            if ($user->is($request->user())) {
                throw new OperationNotAllowedException('cannot suspend the current authenticated user');
            }

            $validator = validator(request()->all())->after([$this, 'validateNotLastActiveAdministrator']);
            if ($validator->fails()) {
                throw new OperationNotAllowedException($validator->errors()->first('last_admin'));
            }

            $user->status = UserStatus::SUSPENDED;
            $user->save();

            return $this->userResponse($user);
        } catch (OperationNotAllowedException $exception) {
            report($exception);
            $code = $exception->getCode();
            $message = 'Invalid operation for current user';
            $httpStatusCode = 400;
        }

        return $this->errorResponse($message, $code, $httpStatusCode);
    }

    /**
     * Reactivate an existing user.
     *
     * @param User $user the user to reactivate
     *
     * @return \Illuminate\Http\JsonResponse the JSON response
     */
    public function reactivate(User $user): JsonResponse
    {
        if (!$user->status->is(UserStatus::SUSPENDED)) {
            return $this->notModifiedResponse();
        }

        $user->status = $user->hasVerifiedEmail() ? UserStatus::ACTIVE : UserStatus::INVITED;
        $user->save();

        return $this->userResponse($user);
    }

    /**
     * Get the super admin users data.
     *
     * @throws \Exception if unable to initialize the datatable
     *
     * @return mixed the response the JSON format
     */
    public function dataJson()
    {
        return DataTables::of(User::whereIs(UserRole::SUPER_ADMIN))
            ->setTransformer(new SuperAdminUserTransformer())
            ->make(true);
    }

    /**
     * Validate user isn't the last active super admin.
     *
     * @param Validator $validator the validator
     */
    public function validateNotLastActiveAdministrator(Validator $validator): void
    {
        $user = request()->route('user');
        if ($user->status->is(UserStatus::ACTIVE) && 1 === User::where('status', UserStatus::ACTIVE)->whereIs(UserRole::SUPER_ADMIN)->count()) {
            $validator->errors()->add('last_admin', 'the last super administrator cannot be suspended');
        }
    }
}