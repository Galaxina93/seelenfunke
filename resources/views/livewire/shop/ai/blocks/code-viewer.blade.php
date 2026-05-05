@php
    $lang = 'text';
    $file = 'Snippet';
    $code = '';

    if (is_array($codeData)) {
        $lang = $codeData['language'] ?? 'text';
        $file = $codeData['file_name'] ?? 'Code Snippet';
        $code = $codeData['code_string'] ?? $codeData['code'] ?? json_encode($codeData, JSON_PRETTY_PRINT);
    } elseif (is_string($codeData)) {
        $code = $codeData;
    }
@endphp
<div class="h-full w-full flex flex-col bg-[#0d1117] rounded-xl border border-gray-700 shadow-2xl relative overflow-hidden">
    <!-- VS Code / Mac Style Header -->
    <div class="bg-[#161b22] px-3 sm:px-4 py-3 border-b border-gray-700 flex items-center justify-between z-10 shrink-0 gap-2 overflow-hidden">
        <div class="flex items-center gap-2 overflow-hidden w-full">
            <!-- Mac window buttons -->
            <div class="hidden sm:flex items-center gap-1.5 mr-4 opacity-50 shrink-0">
                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
            </div>
            
            <i class="bi bi-file-earmark-code text-gray-400 shrink-0"></i>
            <span class="text-xs font-mono font-bold text-gray-300 truncate">{{ $file }}</span>
        </div>
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <span class="text-[10px] font-bold uppercase tracking-widest text-[#58a6ff] bg-[#58a6ff]/10 px-2 py-0.5 rounded-sm border border-[#58a6ff]/20">{{ $lang }}</span>
            <button x-data="{ copied: false }" @click="navigator.clipboard.writeText(`{{ addslashes($code) }}`); copied = true; setTimeout(() => copied = false, 2000)" class="text-gray-400 hover:text-white transition-colors flex items-center gap-1 text-xs font-bold font-sans">
                <span x-show="!copied"><i class="bi bi-clipboard"></i> Copy</span>
                <span x-show="copied" class="text-emerald-400"><i class="bi bi-check2"></i> Copied</span>
            </button>
        </div>
    </div>

    <!-- Code Body -->
    <div class="flex-1 overflow-auto custom-scrollbar p-6 bg-[#0d1117]">
        <pre class="font-mono text-sm leading-relaxed text-[#c9d1d9]"><code class="language-{{ $lang }}">{{ $code }}</code></pre>
    </div>
</div>
