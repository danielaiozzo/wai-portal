<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Enums\UserStatus;
use App\Models\User;
use Carbon\Carbon;
use CodiceFiscale\Calculator;
use CodiceFiscale\Subject;
use Faker\Generator as Faker;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

$factory->define(User::class, function (Faker $faker) {
    return [
        'spid_code' => Str::random(14),
        'name' => $faker->firstName,
        'family_name' => $faker->lastName,
        'fiscal_number' => (
        new Calculator(
            new Subject(
                [
                    'name' => $faker->firstName,
                    'surname' => $faker->lastName,
                    'birthDate' => Carbon::createFromDate(rand(1950, 1990), rand(1, 12), rand(1, 30)),
                    'gender' => rand(0, 1) ? Calculator::CHR_MALE : Calculator::CHR_WOMEN,
                    'belfioreCode' => 'H501',
                ]
            )
        )
        )->calculate(),
        'email' => $faker->unique()->safeEmail,
        'uuid' => Uuid::uuid4()->toString(),
        'password_changed_at' => Carbon::now()->format('Y-m-d H:i:s'),
        'status' => UserStatus::INACTIVE,
    ];
});

$factory->state(User::class, 'invited', [
    'status' => UserStatus::INVITED,
]);

$factory->state(User::class, 'pending', [
    'email_verified_at' => Carbon::now()->format('Y-m-d H:i:s'),
    'status' => UserStatus::PENDING,
]);

$factory->state(User::class, 'active', [
    'email_verified_at' => Carbon::now()->format('Y-m-d H:i:s'),
    'status' => UserStatus::ACTIVE,
]);

$factory->state(User::class, 'suspended', [
    'email_verified_at' => Carbon::now()->format('Y-m-d H:i:s'),
    'status' => UserStatus::SUSPENDED,
]);

$factory->state(User::class, 'password_expired', [
    'password_changed_at' => Carbon::now()->subDays(config('auth.password_expiry') + 1),
]);
