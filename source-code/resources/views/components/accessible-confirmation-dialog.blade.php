@props(['id', 'title', 'danger' => false])

<div x-data="{ open: false }" 
     @open-dialog-{{ $id }}.window="open = true" 
     @keydown.escape.window="open = false" 
     x-cloak>
    
    <!-- Trigger -->
    <div @click="open = true" class="w-full">
        {{ $trigger }}
    </div>

    <!-- Modal Background -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 sm:p-0"
         style="-webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);"
         aria-labelledby="modal-title-{{ $id }}" role="dialog" aria-modal="true">
        
        <!-- Modal Panel -->
        <div x-show="open" 
             @click.away="open = false"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="bg-white rounded-xl shadow-2xl overflow-hidden sm:max-w-md w-full border border-gray-100">
            
            <div class="px-6 py-5">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $danger ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600' }}">
                        <i class="ph {{ $danger ? 'ph-warning' : 'ph-info' }}"></i>
                    </div>
                    <div class="flex-1 mt-0.5">
                        <h3 class="text-lg font-black text-gray-900 tracking-tight" id="modal-title-{{ $id }}">
                            {{ $title }}
                        </h3>
                        <div class="mt-2 text-sm text-gray-500 font-medium leading-relaxed">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50/80 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t border-gray-100">
                <button type="button" @click="open = false" class="w-full sm:w-auto px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-lg hover:bg-gray-50 transition-colors focus:ring-2 focus:ring-gray-200 focus:outline-none">
                    Cancel
                </button>
                <div class="w-full sm:w-auto">
                    {{ $action }}
                </div>
            </div>
        </div>
    </div>
</div>
