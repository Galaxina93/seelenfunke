<div class="m-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="col-span-1 md:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
            <div class="flex justify-center p-6 bg-primary/10">
                <h2 class="text-2xl font-bold text-primary dark:text-primary-light">Login Verlauf</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Letzter Login</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    @foreach($last_logins as $last_login)
                        <tr class="hover:bg-primary/5 transition">
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">
                                {{ $last_login['first_name'] }} {{ $last_login['last_name'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                @if($last_login['last_seen'] == null)
                                    <span class="italic text-gray-400">Noch nie eingeloggt</span>
                                @else
                                    {{ \Carbon\Carbon::parse($last_login['last_seen'])->format('d.m.Y H:i') }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
