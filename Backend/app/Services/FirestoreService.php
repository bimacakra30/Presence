<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;

class FirestoreService
{
    protected $db;

    public function __construct()
    {
        $this->db = new FirestoreClient([
            'keyFilePath' => base_path(env('FIREBASE_CREDENTIALS')),
            'transport' => 'rest',
        ]);
    }

    public function getCollection()
    {
        return $this->db->collection('users');
    }


    public function createUser($id, $data)
    {
        $collection = $this->db->collection('users');
        $collection->document((string)$id)->set($data);
    }

    public function updateUser($id, $data)
    {
        $collection = $this->db->collection('users');
        $collection->document((string)$id)->set($data, ['merge' => true]);
    }

    public function deleteUser($id)
    {
        $collection = $this->db->collection('users');
        $collection->document((string)$id)->delete();
    }

    public function getUsers()
    {
        $collection = $this->db->collection('users');
        $documents = $collection->documents();

        $users = [];
        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $data['id'] = $document->id();
                $users[] = $data;
            }
        }

        return $users;
    }

    public function getAbsensi()
    {
        $firestore = new FirestoreClient([
            'projectId' => 'presence-app-735f3', // Ganti sesuai project ID kamu
        ]);

        $collection = $firestore->collection('absensi');
        $documents = $collection->documents();

        $data = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $docData = $document->data();
                $data[] = array_merge($docData, ['id' => $document->id()]);
            }
        }

        return $data;
    }

     public function deleteAbsensi($id)
    {
        $collection = $this->db->collection('absensi');
        $collection->document((string)$id)->delete();
    }
}
