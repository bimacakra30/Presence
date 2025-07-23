<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;

class FirestoreService
{
    protected $db;

    public function __construct()
    {
        $this->db = new FirestoreClient([
            'keyFilePath' => base_path('storage/app/firebase/firebase_credentials.json'),
            'transport' => 'rest',
        ]);
    }

    public function getCollection()
    {
        return $this->db->collection('employees');
    }


    public function createUser($id, $data)
    {
        $collection = $this->db->collection('employees');
        $collection->document((string)$id)->set($data);
    }

    public function updateUser($id, $data)
    {
        $collection = $this->db->collection('employees');
        $collection->document((string)$id)->set($data, ['merge' => true]);
    }

    public function deleteUser($id)
    {
        $collection = $this->db->collection('employees');
        $collection->document((string)$id)->delete();
    }

    public function getUsers()
    {
        $collection = $this->db->collection('employees');
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
        $collection = $this->db->collection('presence')->documents();
        $absensi = [];

        foreach ($collection as $document) {
            if ($document->exists()) {
                $data = $document->data();

                // Pastikan kita tidak menimpa key penting dan menambahkan ID dokumen
                $absensi[] = array_merge($data, [
                    'firestore_id' => $document->id(),
                ]);
            }
        }

        return $absensi;
    }



     public function deleteAbsensi($documentId)
    {
        $collection = $this->db->collection('presence');
        $collection->document($documentId)->delete();
    }


    public function getCollectionPosition()
    {
        return $this->db->collection('employee_positions');
    }


    public function createUserPosition($id, $data)
    {
        $collection = $this->db->collection('employee_positions');
        $collection->document((string)$id)->set($data);
    }

    public function updateUserPosition($id, $data)
    {
        $collection = $this->db->collection('employee_positions');
        $collection->document((string)$id)->set($data, ['merge' => true]);
    }

    public function deleteUserPosition($id)
    {
        $collection = $this->db->collection('employee_positions');
        $collection->document((string)$id)->delete();
    }

    public function getUsersPosition()
    {
        $collection = $this->db->collection('employee_positions');
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
}
