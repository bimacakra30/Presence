<?php

namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseAuthService
{
    protected $auth;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
        
        $this->auth = $factory->createAuth();
    }

    public function updateEmail($uid, $newEmail)
    {
        $this->auth->updateUser($uid, ['email' => $newEmail]);
    }

    public function getUserByEmail($email)
    {
        return $this->auth->getUserByEmail($email);
    }
}
