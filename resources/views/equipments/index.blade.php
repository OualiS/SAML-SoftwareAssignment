<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Equipments</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <header class="border-b border-slate-200 px-6 py-5">
                <h1 class="text-2xl font-semibold">Equipments</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Search on: Equipment, Material, Description, Room.
                </p>
            </header>

            <div class="px-6 py-4">
                <form method="GET" action="{{ route('equipments') }}" class="flex flex-col gap-3 sm:flex-row">
                    <label for="search" class="sr-only">Search</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        maxlength="100"
                        value="{{ old('search', $search) }}"
                        placeholder="Search an equipment..."
                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm outline-none ring-0 transition focus:border-slate-500"
                    >

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                        >
                            Search
                        </button>

                        @if ($search)
                            <a
                                href="{{ route('equipments') }}"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                            >
                                Reset
                            </a>
                        @endif
                    </div>
                </form>

                @error('search')
                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left font-semibold text-slate-700">Equipment</th>
                            <th scope="col" class="px-6 py-3 text-left font-semibold text-slate-700">Material</th>
                            <th scope="col" class="px-6 py-3 text-left font-semibold text-slate-700">Description</th>
                            <th scope="col" class="px-6 py-3 text-left font-semibold text-slate-700">Room</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($equipments as $equipment)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-medium">{{ $equipment->Equipment }}</td>
                                <td class="px-6 py-3">{{ $equipment->Material ?? '-' }}</td>
                                <td class="px-6 py-3">{{ $equipment->Description ?? '-' }}</td>
                                <td class="px-6 py-3">{{ $equipment->Room ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                    No equipment found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($equipments->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $equipments->links() }}
                </div>
            @endif
        </section>
    </main>
</body>
</html>
