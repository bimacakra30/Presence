<?php

namespace App\Livewire;

use App\Services\FirestoreService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeTable extends Component
{
    use WithPagination;

    public $employees = [];
    public $perPage = 10;
    public $pollInterval; // Polling interval dari environment variable

    public function mount(FirestoreService $firestoreService)
    {
        // Set polling interval berdasarkan konfigurasi realtime
        $realtimeEnabled = env('FIRESTORE_REALTIME_ENABLED', false);
        $this->pollInterval = $realtimeEnabled ? env('LIVEWIRE_POLL_INTERVAL', 10000) : null;
        
        $this->loadEmployees($firestoreService);
    }

    public function loadEmployees(FirestoreService $firestoreService)
    {
        try {
            $result = $firestoreService->getUsers($this->perPage);
            $this->employees = $result['data'];
            session(['firestore_last_employee_document' => $result['lastDocument']]);
        } catch (\Exception $e) {
            Log::error('Error fetching employees from Firestore: ' . $e->getMessage());
            $this->employees = [];
        }
    }

    public function render()
    {
        return view('livewire.employee-table', [
            'employees' => $this->employees, // Teruskan variabel secara eksplisit
            'pollInterval' => $this->pollInterval, // Pastikan variabel ini diteruskan
        ]);
    }
}