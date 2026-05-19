@props(['steps' => [1 => 'Fichier', 2 => 'Mapping', 3 => 'Import']])
<div class="flex items-center gap-1 mb-6">
    @foreach ($steps as $i => $label)
    <div class="flex items-center gap-2">
        <span class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-mono transition-colors"
              :class="step >= {{ $i }} ? 'bg-[var(--accent)] text-white' : 'bg-[var(--surface2)] text-[var(--text3)]'">{{ $i }}</span>
        <span class="text-[12px] transition-colors"
              :class="step === {{ $i }} ? 'text-primary font-medium' : 'text-[var(--text3)]'">{{ $label }}</span>
        @if (! $loop->last)
        <span class="w-10 h-px mx-1" style="background:var(--border)"></span>
        @endif
    </div>
    @endforeach
</div>
