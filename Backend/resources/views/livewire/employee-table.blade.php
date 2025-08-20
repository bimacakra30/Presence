@props([
    'firebaseConfig' => [],
])

<div x-data="{ employees: [] }" x-init="
    // Initialize Firebase
    firebase.initializeApp(@json($firebaseConfig));
    const db = firebase.firestore();
    const employeesRef = db.collection('employees');

    // Real-time listener
    employeesRef.onSnapshot((snapshot) => {
        let updatedEmployees = [];
        snapshot.forEach((doc) => {
            updatedEmployees.push({
                id: doc.id,
                ...doc.data()
            });
        });
        employees = updatedEmployees;

        // Update Filament table body
        const tbody = document.querySelector('.filament-tables-table tbody');
        if (tbody) {
            tbody.innerHTML = ''; // Clear existing rows
            updatedEmployees.forEach(employee => {
                const row = `
                    <tr>
                        <td>
                            ${employee.profilePictureUrl ? `<img src='${employee.profilePictureUrl}' class='h-12 w-12 rounded-full' />` : ''}
                        </td>
                        <td>${employee.name}</td>
                        <td>${employee.username}</td>
                        <td>${employee.email}</td>
                        <td>${employee.phone || ''}</td>
                        <td>${employee.dateOfBirth || ''}</td>
                        <td>${employee.position}</td>
                        <td>${employee.provider}</td>
                        <td>
                            <span class='filament-tables-badge ${employee.status === 'aktif' ? 'bg-green-500' : employee.status === 'non-aktif' ? 'bg-yellow-500' : 'bg-red-500'} text-white px-2 py-1 rounded'>
                                ${employee.status}
                            </span>
                        </td>
                        <td>${employee.createdAt}</td>
                        <td>
                            <a href='/admin/employees/${employee.id}/edit' class='filament-button filament-button-size-sm'>Edit</a>
                            <button onclick='deleteEmployee(\"${employee.id}\")' class='filament-button filament-button-size-sm text-red-500'>Delete</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    });

    // Delete function
    window.deleteEmployee = function(id) {
        if (confirm('Are you sure you want to delete this employee?')) {
            db.collection('employees').doc(id).delete()
                .then(() => {
                    console.log('Employee deleted from Firestore');
                    // Sync with Eloquent via API
                    fetch('/api/employees/delete/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                        }
                    }).then(response => console.log('Eloquent synced'));
                })
                .catch(error => console.error('Error deleting employee:', error));
        }
    };
">
    <!-- Include Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore.js"></script>
</div>