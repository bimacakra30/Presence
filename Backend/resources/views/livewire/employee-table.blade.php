<div wire:poll.{{ isset($pollInterval) ? $pollInterval : 10000 }}ms="loadEmployees">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posisi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @if (isset($employees) && is_array($employees) && count($employees) > 0)
                @foreach ($employees as $employee)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img src="{{ $employee['profilePictureUrl'] ?? 'https://via.placeholder.com/50' }}" alt="Photo" class="rounded-full h-12 w-12">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $employee['name'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $employee['email'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $employee['position'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $employee['status'] ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center">Tidak ada data karyawan.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>