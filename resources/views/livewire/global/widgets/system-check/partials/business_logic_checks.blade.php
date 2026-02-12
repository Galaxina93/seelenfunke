<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @foreach($checks as $check)
        <div class="relative group bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all duration-300">

            {{-- Status Indicator Bar --}}
            <div class="absolute top-0 left-0 w-full h-1
                        {{ $check['status'] === 'success' ? 'bg-emerald-400' : '' }}
                        {{ $check['status'] === 'warning' ? 'bg-amber-400' : '' }}
                        {{ $check['status'] === 'danger' ? 'bg-rose-500' : '' }}
                        {{ $check['status'] === 'info' ? 'bg-sky-400' : '' }}">
            </div>

            <div class="flex justify-between items-start mb-3">
                <div class="p-2.5 rounded-xl
                            {{ $check['status'] === 'success' ? 'bg-emerald-50 text-emerald-600' : '' }}
                            {{ $check['status'] === 'warning' ? 'bg-amber-50 text-amber-600' : '' }}
                            {{ $check['status'] === 'danger' ? 'bg-rose-50 text-rose-600' : '' }}
                            {{ $check['status'] === 'info' ? 'bg-sky-50 text-sky-600' : '' }}">
                    <i class="{{ $check['icon'] }} text-2xl"></i>
                </div>
                @if($check['count'] > 0)
                    <span class="font-bold text-lg text-slate-800">{{ $check['count'] }}</span>
                @endif
            </div>

            <h3 class="font-bold text-slate-700">{{ $check['title'] }}</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4 line-clamp-2 min-h-[2.5em]">{{ $check['message'] }}</p>

            <a href="{{ $check['action_url'] }}" class="text-xs font-semibold flex items-center gap-1
                        {{ $check['status'] === 'danger' ? 'text-rose-600 hover:text-rose-800' : 'text-indigo-600 hover:text-indigo-800' }}">
                {{ $check['action_label'] }}
                <svg class="w-3 h-3 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    @endforeach
</div>
