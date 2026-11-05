@props([])

{{-- OSAS Floating Chat Widget -- visible on every OSAS page, bottom-right. --}}
<div
    x-data="osasFloatingChat()"
    x-init="init()"
    x-cloak
    @click.away="trayGuardedClose()"
    class="fixed bottom-6 right-6 z-[9000] flex flex-col items-end"
>
    {{-- Chat Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-6 scale-90"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 scale-90"
        class="mb-4 w-96 max-w-[calc(100vw-2rem)] h-[560px] max-h-[calc(100vh-8rem)] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden flex flex-col"
    >
        {{-- Header --}}
        <div class="px-5 py-4 bg-gradient-to-br from-[#26488C] to-[#1a3461] flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center flex-shrink-0 overflow-hidden">
                    <img src="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png" alt="OSAS"
                         @@error="$event.target.style.display='none'"
                         class="w-7 h-7 object-contain">
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-black text-white leading-tight">OSAS Communications</h3>
                    <p class="text-[9px] font-bold text-white/60 uppercase tracking-widest mt-0.5">Direct messaging with campus organizations</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <template x-if="view === 'chat'">
                    <button @click="backToOrgs()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all" title="Back to organizations">
                        <i class="ph ph-arrow-left text-sm"></i>
                    </button>
                </template>
                <button @click="open = false" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all" title="Close">
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-hidden flex flex-col min-h-0 bg-slate-50/40">
            {{-- ===== Org List View ===== --}}
            <template x-if="view === 'orgs'">
                <div class="flex flex-col h-full min-h-0">
                    {{-- Broadcast mode toolbar --}}
                    <template x-if="selectMode">
                        <div class="px-4 py-3 border-b border-gray-100 bg-[#26488C]/5 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <i class="ph ph-megaphone text-sm text-[#26488C]"></i>
                                <span class="text-[10px] font-black text-[#26488C] uppercase tracking-widest">Select recipients</span>
                            </div>
                            <button @click="toggleSelectAll()" class="text-[10px] font-black text-[#26488C] hover:text-[#1a3461] uppercase tracking-widest transition-colors">
                                <span x-text="allSelected ? 'Clear All' : 'Select All'"></span>
                            </button>
                        </div>
                    </template>

                    {{-- Search --}}
                    <div class="p-4 pb-2 flex-shrink-0" x-show="!selectMode">
                        <div class="relative">
                            <i class="ph ph-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-300"></i>
                            <input type="text" x-model="searchQuery" placeholder="Search organizations..."
                                   class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-100 rounded-lg text-xs font-bold placeholder:text-gray-300 focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C]/30 transition-all">
                        </div>
                    </div>

                    {{-- Org list --}}
                    <div class="flex-1 overflow-y-auto custom-scrollbar py-1">
                        <template x-if="loadingOrgs && filteredOrgs.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-gray-300">
                                <i class="ph ph-chats text-3xl mb-2"></i>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Loading conversations...</p>
                            </div>
                        </template>

                        <template x-for="org in filteredOrgs" :key="org.id">
                            <div
                                @click="selectMode ? toggleOrg(org.id) : openOrg(org)"
                                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-all hover:bg-white"
                                :class="selectMode && selectedOrgIds.includes(org.id) ? 'bg-[#26488C]/10' : ''"
                            >
                                {{-- Broadcast checkbox --}}
                                <div x-show="selectMode"
                                     class="w-5 h-5 rounded-md border-2 flex items-center justify-center flex-shrink-0 transition-all"
                                     :class="selectedOrgIds.includes(org.id) ? 'bg-[#26488C] border-[#26488C]' : 'bg-white border-gray-200'">
                                    <i x-show="selectedOrgIds.includes(org.id)" class="ph ph-check text-[10px] text-white font-black"></i>
                                </div>

                                {{-- Avatar / Logo --}}
                                <div class="relative flex-shrink-0">
                                    <template x-if="org.logo_url">
                                        <img :src="org.logo_url" :alt="org.name + ' logo'"
                                             @@error="org.logo_url = null"
                                             class="w-10 h-10 rounded-xl object-contain bg-white border border-gray-100 p-1 shadow-sm">
                                    </template>
                                    <template x-if="!org.logo_url">
                                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#26488C] flex items-center justify-center font-black text-sm">
                                            <span x-text="org.name.charAt(0).toUpperCase()"></span>
                                        </div>
                                    </template>
                                    <span x-show="org.unread_count > 0 && !(selectMode && selectedOrgIds.includes(org.id))"
                                          class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[9px] font-black rounded-full flex items-center justify-center border-2 border-white"
                                          x-text="org.unread_count"></span>
                                </div>

                                {{-- Name + last message --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-black text-gray-900 truncate" x-text="org.name"></h4>
                                        <span class="text-[9px] text-gray-300 font-bold flex-shrink-0" x-text="formatTime(org.last_message_time)"></span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 font-bold truncate mt-0.5">
                                        <span x-show="org.last_message_from_osas === true" class="text-[#26488C]">You: </span>
                                        <span x-show="org.last_message" x-text="org.last_message"></span>
                                        <span x-show="!org.last_message" class="text-gray-300">No messages yet</span>
                                    </p>
                                </div>

                                {{-- Thread arrow --}}
                                <i x-show="!selectMode" class="ph ph-caret-right text-xs text-gray-200 flex-shrink-0"></i>
                            </div>
                        </template>

                        <template x-if="!loadingOrgs && filteredOrgs.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-gray-300">
                                <i class="ph ph-magnifying-glass text-3xl mb-2"></i>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest" x-text="searchQuery ? 'No organizations found' : 'No organizations yet'"></p>
                            </div>
                        </template>
                    </div>

                    {{-- Broadcast composer --}}
                    <template x-if="selectMode">
                        <div class="p-4 border-t border-gray-100 bg-white flex-shrink-0">
                            <div class="flex justify-end mb-2" x-show="broadcastAttachment" x-cloak>
                                <div id="osas-broadcast-preview" class="relative inline-flex items-center p-1.5 bg-gray-50 border border-gray-200 rounded-xl group">
                                    <img x-show="broadcastAttachment && broadcastAttachment.type.indexOf('image/') === 0"
                                         :src="broadcastPreviewUrl" class="w-14 h-14 object-cover rounded-lg border border-gray-100" alt="Attachment preview">
                                    <span x-show="broadcastAttachment && broadcastAttachment.type.indexOf('image/') !== 0"
                                          class="inline-flex items-center justify-center min-w-[48px] px-3 py-2 rounded-lg text-white text-[10px] font-black tracking-widest"
                                          :style="broadcastAttachment ? 'background:' + fileTypeColor(broadcastAttachment.name) + ';' : ''"
                                          x-text="broadcastAttachment ? fileTypeLabel(broadcastAttachment.name) : ''"></span>
                                    <button type="button" @click="clearBroadcastAttachment()" class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-white border border-gray-200 shadow-sm text-gray-500 hover:text-red-500 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" title="Remove attachment"><i class="ph ph-x text-[10px]"></i></button>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="cursor-pointer w-10 h-10 shrink-0 rounded-lg bg-gray-50 border border-gray-100 text-gray-400 hover:text-[#26488C] hover:border-[#26488C]/30 flex items-center justify-center transition-all" title="Attach a file">
                                    <i class="ph ph-paperclip text-sm"></i>
                                    <input type="file" id="osas-broadcast-attachment-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="hidden" @@change="pickBroadcastAttachment($event)">
                                </label>
                                <input type="text" x-model="messageText" @keydown.enter.prevent="sendBroadcast()"
                                       placeholder="Broadcast message to selected orgs..."
                                       class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-lg text-xs font-bold placeholder:text-gray-300 focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C]/30 transition-all">
                                <button @click="sendBroadcast()"
                                        :disabled="sending || selectedOrgIds.length === 0 || (!messageText.trim() && !broadcastAttachment)"
                                        class="w-10 h-10 rounded-lg bg-[#26488C] text-white flex items-center justify-center transition-all hover:scale-105 active:scale-95 disabled:opacity-40 disabled:hover:scale-100 shadow-lg shadow-[#26488C]/20 flex-shrink-0">
                                    <i x-show="!sending" class="ph ph-paper-plane-tilt text-sm"></i>
                                    <i x-show="sending" class="ph ph-circle-notch text-sm ph-spin"></i>
                                </button>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-[9px] font-black text-gray-300 uppercase tracking-widest">
                                    <span x-text="selectedOrgIds.length"></span> of <span x-text="organizations.length"></span> organizations selected
                                </span>
                                <button @click="selectMode = false; selectedOrgIds = []" class="text-[9px] font-black text-gray-400 hover:text-[#26488C] uppercase tracking-widest transition-colors">Cancel</button>
                            </div>
                        </div>
                    </template>

                    {{-- Footer: Broadcast entry --}}
                    <template x-if="!selectMode">
                        <div class="p-4 border-t border-gray-100 bg-white flex-shrink-0">
                            <button @click="selectMode = true; messageText = ''"
                                    class="w-full py-2.5 rounded-lg border border-dashed border-[#26488C]/30 text-[#26488C] text-[10px] font-black uppercase tracking-widest hover:bg-[#26488C]/5 transition-all flex items-center justify-center gap-2">
                                <i class="ph ph-megaphone text-xs"></i>
                                Broadcast to Organizations
                            </button>
                        </div>
                    </template>
                </div>
            </template>

            {{-- ===== Chat View ===== --}}
            <template x-if="view === 'chat' && selectedOrg">
                <div class="flex flex-col h-full min-h-0">
                    {{-- Conversation header --}}
                    <div class="px-4 py-3 bg-white border-b border-gray-100 flex items-center gap-3 flex-shrink-0">
                        <template x-if="selectedOrg.logo_url">
                            <img :src="selectedOrg.logo_url" :alt="selectedOrg.name + ' logo'"
                                 @@error="selectedOrg.logo_url = null"
                                 class="w-9 h-9 rounded-lg object-contain bg-white border border-gray-100 p-0.5 shadow-sm flex-shrink-0">
                        </template>
                        <template x-if="!selectedOrg.logo_url">
                            <div class="w-9 h-9 rounded-lg bg-[#26488C] text-white flex items-center justify-center font-black text-xs flex-shrink-0">
                                <span x-text="selectedOrg.name.charAt(0).toUpperCase()"></span>
                            </div>
                        </template>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs font-black text-gray-900 truncate" x-text="selectedOrg.name"></h4>
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Connected</span>
                            </div>
                        </div>
                    </div>

                    {{-- Messages --}}
                    <div class="relative flex-1 min-h-0 flex flex-col">
                        <div id="osas-floating-chat-messages" class="flex-1 min-h-0 overflow-y-auto custom-scrollbar p-5 space-y-4 bg-slate-50/40">
                            <div class="flex justify-center pb-1" x-show="hasMoreHistory && messages.length > 0" x-cloak>
                                <button type="button" @click="loadEarlierHistory()" :disabled="loadingEarlier"
                                        class="px-4 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#26488C] bg-white border border-gray-100 rounded-full shadow-sm hover:bg-gray-50 transition-all disabled:opacity-60">
                                    <span x-text="loadingEarlier ? 'Loading...' : 'Load earlier messages'"></span>
                                </button>
                            </div>

                            <template x-if="loadingMessages">
                                <div class="flex justify-center py-6">
                                    <div class="w-6 h-6 border-2 border-[#26488C]/20 border-t-[#26488C] rounded-full animate-spin"></div>
                                </div>
                            </template>

                        <template x-for="(msg, index) in messages" :key="msg.id">
                            <div class="flex" :class="msg.sender_type === 'osas' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[80%]">
                                    <div class="flex items-center gap-2 mb-1" :class="msg.sender_type === 'osas' ? 'flex-row-reverse' : 'flex-row'">
                                        <template x-if="msg.sender_type === 'osas'">
                                            <img src="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png" alt="OSAS"
                                                 @@error="$event.target.style.display='none'"
                                                 class="w-4 h-4 rounded object-contain flex-shrink-0">
                                        </template>
                                        <template x-if="msg.sender_type === 'org' && selectedOrg.logo_url">
                                            <img :src="selectedOrg.logo_url" :alt="selectedOrg.name + ' logo'"
                                                 class="w-4 h-4 rounded object-contain flex-shrink-0">
                                        </template>
                                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest"
                                              x-text="msg.sender_type === 'osas' ? 'OSAS (You)' : senderName(msg)"></span>
                                        <span x-show="String(msg.id).startsWith('temp-')"
                                              class="text-[9px] font-black text-[#C4973B] uppercase tracking-widest">Sending...</span>
                                        <span x-show="!String(msg.id).startsWith('temp-') && shouldShowThreadTime(msg, index)"
                                              class="text-[9px] text-gray-300 font-bold" x-text="formatTime(msg.created_at)"></span>
                                    </div>
                                    <div class="px-4 py-2.5 rounded-xl text-xs font-medium shadow-sm break-words"
                                         :class="imageOnlyMessage(msg)
                                            ? 'osas-bubble-image-only'
                                            : (msg.sender_type === 'osas'
                                                ? 'bg-[#26488C] text-white rounded-tr-none shadow-[#26488C]/20'
                                                : 'bg-white border border-gray-100 text-gray-700 rounded-tl-none')">
                                        <span x-text="msg.message"></span>
                                        <div x-html="attachmentHtml(msg)"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                            <template x-if="!loadingMessages && messages.length === 0">
                                <div class="flex flex-col items-center justify-center py-8 text-gray-300">
                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                                        <i class="ph ph-chats text-2xl text-gray-300"></i>
                                    </div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">No messages yet</p>
                                    <p class="text-[10px] text-gray-300 mt-1">Start the conversation with this organization</p>
                                </div>
                            </template>
                        </div>
                        <button x-show="showThreadJumpLatest" x-cloak @click="jumpThreadToLatest()" type="button"
                                aria-label="Jump to latest messages" title="Jump to latest messages"
                                class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 w-9 h-9 rounded-full bg-white border border-gray-100 hover:scale-105 active:scale-95 transition-all duration-200 flex items-center justify-center text-[#26488C]">
                            <i class="ph ph-arrow-down text-sm"></i>
                        </button>
                    </div>

                    {{-- Input --}}
                    <div class="p-4 bg-white border-t border-gray-100 flex-shrink-0">
                        <div class="flex justify-end mb-2" x-show="threadAttachment" x-cloak>
                            <div id="osas-thread-preview" class="relative inline-flex items-center p-1.5 bg-gray-50 border border-gray-200 rounded-xl group">
                                <img x-show="threadAttachment && threadAttachment.type.indexOf('image/') === 0"
                                     :src="threadPreviewUrl" class="w-14 h-14 object-cover rounded-lg border border-gray-100" alt="Attachment preview">
                                <span x-show="threadAttachment && threadAttachment.type.indexOf('image/') !== 0"
                                      class="inline-flex items-center justify-center min-w-[48px] px-3 py-2 rounded-lg text-white text-[10px] font-black tracking-widest"
                                      :style="threadAttachment ? 'background:' + fileTypeColor(threadAttachment.name) + ';' : ''"
                                      x-text="threadAttachment ? fileTypeLabel(threadAttachment.name) : ''"></span>
                                <button type="button" @click="clearThreadAttachment()" class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-white border border-gray-200 shadow-sm text-gray-500 hover:text-red-500 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" title="Remove attachment"><i class="ph ph-x text-[10px]"></i></button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer w-10 h-10 shrink-0 rounded-lg bg-gray-50 border border-gray-100 text-gray-400 hover:text-[#26488C] hover:border-[#26488C]/30 flex items-center justify-center transition-all" title="Attach a file">
                                <i class="ph ph-paperclip text-sm"></i>
                                <input type="file" id="osas-thread-attachment-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="hidden" @@change="pickThreadAttachment($event)">
                            </label>
                            <input type="text" x-model="messageText" @keydown.enter.prevent="sendToThread()"
                                   placeholder="Type a message..."
                                   class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-lg text-xs font-bold placeholder:text-gray-300 focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C]/30 transition-all">
                            <button @click="sendToThread()"
                                    :disabled="sending || (!messageText.trim() && !threadAttachment)"
                                    class="w-10 h-10 rounded-lg bg-[#26488C] text-white flex items-center justify-center transition-all hover:scale-105 active:scale-95 disabled:opacity-40 disabled:hover:scale-100 shadow-lg shadow-[#26488C]/20 flex-shrink-0">
                                <i x-show="!sending" class="ph ph-paper-plane-tilt text-sm"></i>
                                <i x-show="sending" class="ph ph-circle-notch text-sm ph-spin"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Floating Action Button --}}
    <button
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-label="OSAS Communications chat"
        class="relative w-14 h-14 rounded-2xl bg-gradient-to-br from-[#26488C] to-[#1a3461] shadow-xl shadow-[#26488C]/30 hover:shadow-2xl hover:shadow-[#26488C]/40 hover:scale-105 active:scale-95 transition-all duration-300 flex items-center justify-center text-white"
    >
        <i class="ph ph-chats text-2xl transition-all duration-300 absolute"
           :class="open ? 'opacity-0 rotate-90 scale-50' : 'opacity-100 rotate-0 scale-100'"></i>
        <i class="ph ph-x text-2xl transition-all duration-300 absolute"
           :class="open ? 'opacity-100 rotate-0 scale-100' : 'opacity-0 -rotate-90 scale-50'"></i>

        {{-- Unread badge --}}
        <span x-show="totalUnread > 0 && !open"
              class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm"
              x-text="totalUnread > 99 ? '99+' : totalUnread"></span>
    </button>

    {{-- Image lightbox --}}
    <div x-show="lightboxUrl" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/55 modal-backdrop"
         @click.self="lightboxUrl = null" @keydown.escape.window="lightboxUrl = null" role="dialog" aria-modal="true">
        <button type="button" class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/15 border border-white/30 text-white text-xl flex items-center justify-center hover:bg-white/30 transition-colors"
                @click="lightboxUrl = null" title="Close image" aria-label="Close image">
            <i class="ph ph-x"></i>
        </button>
        <img :src="lightboxUrl" class="max-w-[90vw] max-h-[90vh] rounded-2xl shadow-2xl object-contain" alt="Attachment">
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 8px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #d1d5db; }

    /* Image-only messages render as a bare image with no bubble box */
    .osas-bubble-image-only {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
</style>

<script>
    function osasFloatingChat() {
        return {
            open: false,
            view: 'orgs',
            organizations: [],
            selectedOrg: null,
            messages: [],
            lastId: 0,
            messageText: '',
            searchQuery: '',
            loadingOrgs: false,
            loadingMessages: false,
            sending: false,
            selectMode: false,
            selectedOrgIds: [],
            totalUnread: 0,
            realtimeOff: null,
            threadAttachment: null,
            threadPreviewUrl: null,
            broadcastAttachment: null,
            broadcastPreviewUrl: null,
            lightboxUrl: null,
            lightboxListener: null,
            hasMoreHistory: false,
            oldestId: 0,
            loadingEarlier: false,
            showThreadJumpLatest: false,
            // True while a programmatic open (e.g. calendar MESSAGE button,
            // which lives OUTSIDE this widget) is still dispatching its
            // click — prevents @click.away from instantly closing the tray.
            justOpenedProgrammatically: false,

            init() {
                this.refreshUnread();
                this.loadOrgs();

                // Image lightbox: any [data-lightbox] click (temp or confirmed
                // bubbles) opens the modal instead of navigating away.
                this.lightboxListener = (e) => {
                    const el = e.target.closest('[data-lightbox]');
                    if (el) {
                        e.preventDefault();
                        this.lightboxUrl = el.dataset.lightbox;
                    }
                };
                document.addEventListener('click', this.lightboxListener);

                // No HTTP polling: Supabase Realtime streams new chat messages
                // from the database and handleRealtimeMessage() updates the
                // badge and message threads instantly (see below).
                // window.ChatRealtime may not exist yet if the app bundle is
                // still loading, so retry until it becomes available.
                this.bindChatRealtime();

                // External opener: lets other OSAS widgets (e.g. the Master
                // Calendar event modal) jump straight into an org thread
                // without leaving the page.
                this.__osasOpenChatListener = (e) => {
                    const orgId = e && e.detail ? e.detail.orgId : null;
                    const fallback = e && e.detail ? (e.detail.fallback || null) : null;
                    if (orgId !== null && orgId !== undefined) {
                        this.openChatById(orgId, fallback);
                    }
                };
                this.__osasOpenPanelListener = () => {
                    this.openChatPanel();
                };
                window.addEventListener('osas:open-chat', this.__osasOpenChatListener);
                window.addEventListener('osas:open-chat-panel', this.__osasOpenPanelListener);
            },

            bindChatRealtime() {
                const bind = () => {
                    if (!window.ChatRealtime || typeof window.ChatRealtime.onMessage !== 'function') {
                        return false;
                    }
                    this.realtimeOff = window.ChatRealtime.onMessage((row) => {
                        if (!row || row.channel !== 'osas') return;
                        this.handleRealtimeMessage(row);
                    });
                    return true;
                };

                if (bind()) return;

                const timer = setInterval(() => {
                    if (bind()) clearInterval(timer);
                }, 250);
                setTimeout(() => clearInterval(timer), 30000);
            },

            destroy() {
                if (this.lightboxListener) document.removeEventListener('click', this.lightboxListener);
                if (this.__osasOpenChatListener) window.removeEventListener('osas:open-chat', this.__osasOpenChatListener);
                if (this.__osasOpenPanelListener) window.removeEventListener('osas:open-chat-panel', this.__osasOpenPanelListener);
                if (this.realtimeOff) this.realtimeOff();
            },

            get filteredOrgs() {
                const q = this.searchQuery.trim().toLowerCase();
                if (!q) return this.organizations;
                return this.organizations.filter(org =>
                    (org.name || '').toLowerCase().includes(q)
                    || (org.org_acronym || '').toLowerCase().includes(q)
                );
            },

            get allSelected() {
                return this.organizations.length > 0
                    && this.selectedOrgIds.length === this.organizations.length;
            },

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    if (this.organizations.length === 0) this.loadOrgs();
                    this.refreshUnread();
                }
            },

            trayGuardedClose() {
                if (this.justOpenedProgrammatically) return;
                this.open = false;
            },

            markProgrammaticOpen() {
                this.justOpenedProgrammatically = true;
                this.$nextTick(() => { this.justOpenedProgrammatically = false; });
                setTimeout(() => { this.justOpenedProgrammatically = false; }, 400);
            },

            openChatPanel() {
                this.markProgrammaticOpen();
                this.open = true;
                if (this.organizations.length === 0) this.loadOrgs();
                this.refreshUnread();
            },

            async openChatById(orgId, fallback = null) {
                this.markProgrammaticOpen();
                this.open = true;
                this.refreshUnread();
                if (this.organizations.length === 0) {
                    await this.loadOrgs(false);
                }
                const numericId = Number(orgId);
                let org = this.organizations.find(o =>
                    o.id === orgId || o.id === numericId || String(o.id) === String(orgId)
                );
                if (!org && fallback) {
                    org = {
                        id: fallback.id !== undefined ? fallback.id : orgId,
                        name: fallback.name || 'Organization',
                        org_acronym: fallback.org_acronym || null,
                        logo_url: fallback.logo_url || null,
                        messages: [],
                        unread_count: 0,
                        has_more: false,
                        oldest_id: 0,
                    };
                }
                if (org) {
                    this.openOrg(org);
                } else if (this.organizations.length === 0) {
                    // Org list still unavailable; stay on the org list view so
                    // the user can pick the conversation manually.
                    this.view = 'orgs';
                }
            },

            async loadOrgs(showLoader = true) {
                if (showLoader) this.loadingOrgs = true;
                try {
                    const response = await fetch("{{ route('osas.chat.orgs') }}", {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.organizations) {
                        this.organizations = data.organizations;
                        this.selectedOrgIds = this.selectedOrgIds.filter(id =>
                            this.organizations.some(org => org.id === id)
                        );
                    }
                } catch (error) {
                    console.error('OSAS chat: failed to load organizations', error);
                } finally {
                    if (showLoader) this.loadingOrgs = false;
                }
            },

            async refreshUnread() {
                try {
                    const response = await fetch("{{ route('osas.chat.unread-count') }}", {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (typeof data.count === 'number') this.totalUnread = data.count;
                } catch (error) {
                    // silently ignore
                }
            },

            async openOrg(org) {
                this.selectedOrg = org;
                this.view = 'chat';
                this.messageText = '';
                this.hasMoreHistory = !!(org.has_more || false);
                this.oldestId = org.oldest_id || 0;

                // Render the thread IMMEDIATELY from the message history already
                // bundled in the organizations payload, then refresh it in the
                // background so nothing is ever stale. No blank panel.
                this.messages = org.messages || [];
                this.lastId = this.messages.length > 0
                    ? this.messages[this.messages.length - 1].id
                    : 0;
                org.unread_count = 0;
                this.refreshUnread();
                this.$nextTick(() => {
                    this.scrollToBottom(true);
                    this.attachThreadScrollListener();
                });

                // Background refresh with last_id=0: pulls anything the cache
                // missed and marks the counterpart's messages as read server-side.
                this.fetchNewMessages(0);
            },

            backToOrgs() {
                this.view = 'orgs';
                this.selectedOrg = null;
                this.messages = [];
                this.lastId = 0;
                this.messageText = '';
                this.loadOrgs(false);
            },

            async fetchNewMessages(lastIdOverride = null) {
                if (!this.selectedOrg || this.sending) return;
                const lastId = lastIdOverride ?? this.lastId;
                try {
                    const response = await fetch(
                        "{{ route('osas.chat.messages') }}?organization_id=" + this.selectedOrg.id + "&last_id=" + lastId,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }
                    );
                    const data = await response.json();
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            if (this.messages.some(m => m.id === msg.id)) return;
                            if (this.hasPendingTwin(msg)) return;
                            this.messages.push(msg);
                            this.pushToCache(this.selectedOrg, msg);
                        });
                        this.lastId = data.messages[data.messages.length - 1].id;
                        this.selectedOrg.unread_count = 0;
                        this.refreshUnread();
                        this.$nextTick(() => this.scrollToBottom(true));
                    }
                } catch (error) {
                    // silently ignore
                }
            },

            async loadEarlierHistory() {
                if (this.loadingEarlier || !this.selectedOrg) return;
                if (!this.oldestId || !this.hasMoreHistory) return;

                this.loadingEarlier = true;
                try {
                    const response = await fetch(
                        "{{ route('osas.chat.history') }}?organization_id=" + this.selectedOrg.id + "&before_id=" + this.oldestId,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }
                    );
                    const data = await response.json();
                    if (data.success && data.messages.length > 0) {
                        // Keep the view pinned to the first currently visible
                        // message so older history appears above it.
                        const container = document.getElementById('osas-floating-chat-messages');
                        const anchor = container ? container.querySelector('[data-msg-id]') : null;
                        const anchorTop = anchor ? anchor.getBoundingClientRect().top - container.getBoundingClientRect().top : 0;

                        const frag = document.createDocumentFragment();
                        data.messages.forEach(msg => {
                            const div = document.createElement('div');
                            div.dataset.msgId = msg.id;
                            div.className = 'flex ' + (msg.sender_type === 'osas' ? 'justify-end' : 'justify-start');
                            const isFromOsas = msg.sender_type === 'osas';
                            const time = this.formatTime(msg.created_at);
                            const senderName = isFromOsas ? 'OSAS (You)' : this.senderName(msg);
                            div.innerHTML = `
                                <div class="max-w-[80%]">
                                    <div class="flex items-center gap-2 mb-1 ${isFromOsas ? 'flex-row-reverse' : 'flex-row'}">
                                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">${senderName}</span>
                                        <span class="text-[9px] text-gray-300 font-bold">${time}</span>
                                    </div>
                                    <div class="px-4 py-2.5 rounded-xl text-xs font-medium shadow-sm break-words ${isFromOsas
                                        ? 'bg-[#26488C] text-white rounded-tr-none shadow-[#26488C]/20'
                                        : 'bg-white border border-gray-100 text-gray-700 rounded-tl-none'}">
                                        <span>${String(msg.message || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                                        <div>${this.attachmentHtml(msg)}</div>
                                    </div>
                                </div>`;
                            frag.appendChild(div);
                        });

                        if (anchor && container) {
                            container.insertBefore(frag, anchor);
                            const pinned = container.querySelector('[data-msg-id="' + anchor.dataset.msgId + '"]');
                            if (pinned) {
                                container.scrollTop += pinned.getBoundingClientRect().top - container.getBoundingClientRect().top - anchorTop;
                            }
                        } else if (container) {
                            container.prepend(frag);
                        }

                        this.oldestId = data.messages[0].id;
                        this.hasMoreHistory = !!(data.has_more || false);
                    } else if (data.success) {
                        this.hasMoreHistory = false;
                    }
                } catch (error) {
                    // silently ignore
                } finally {
                    this.loadingEarlier = false;
                }
            },

            pushToCache(org, row) {
                if (!org || !org.messages) return;
                if (org.messages.some(m => m.id === row.id)) return;
                if (org.messages.some(m =>
                    String(m.id).startsWith('temp-') &&
                    m.sender_type === row.sender_type &&
                    m.message === row.message &&
                    Boolean(m.attachment_path) === Boolean(row.attachment_path)
                )) return;
                org.messages.push(row);
            },

            hasPendingTwin(msg) {
                return this.messages.some(m =>
                    String(m.id).startsWith('temp-') &&
                    m.sender_type === msg.sender_type &&
                    m.message === msg.message &&
                    Boolean(m.attachment_path) === Boolean(msg.attachment_path)
                );
            },

            async sendToThread() {
                const text = this.messageText.trim();
                const attachmentFile = this.threadAttachment;
                if ((!text && !attachmentFile) || this.sending || !this.selectedOrg) return;

                // Fly the preview into the chat, then clear the composer
                // immediately so the preview disappears on send.
                if (attachmentFile) {
                    this.flyAttachmentToChat('osas-thread-preview');
                }
                this.messageText = '';
                this.clearThreadAttachment();

                this.sending = true;
                try {
                    // When text and an attachment are combined, they are sent
                    // as two separate messages so the attachment gets its own
                    // bubble (text first, then the attachment).
                    if (text && attachmentFile) {
                        await this.sendThreadSingle(text, null);
                        await this.sendThreadSingle('', attachmentFile);
                    } else {
                        await this.sendThreadSingle(text, attachmentFile);
                    }
                } finally {
                    this.sending = false;
                }
            },

            async sendThreadSingle(text, attachmentFile) {
                const hasFile = !!attachmentFile;

                // Capture a fresh object URL for the optimistic bubble BEFORE
                // the composer preview is cleared (same behaviour as the admin
                // chat) so the image stays visible in the conversation.
                const isImageFile = hasFile && attachmentFile.type.indexOf('image/') === 0;
                const tempPreviewUrl = isImageFile ? URL.createObjectURL(attachmentFile) : null;

                // Optimistic update: show the message immediately, then
                // replace the temp copy with the server-confirmed message.
                const tempMessage = {
                    id: 'temp-' + Date.now(),
                    message: text,
                    sender_type: 'osas',
                    created_at: new Date().toISOString(),
                    organization_id: this.selectedOrg.id,
                    sender: { first_name: 'OSAS', last_name: '' },
                    attachment_path: hasFile ? 'pending' : null,
                    attachment_url: tempPreviewUrl,
                    is_image: isImageFile
                };

                this.messages.push(tempMessage);
                this.$nextTick(() => this.scrollToBottom(true));

                try {
                    const formData = new FormData();
                    formData.append('_token', this.csrfToken());
                    formData.append('message', text);
                    formData.append('organization_ids[]', this.selectedOrg.id);
                    if (hasFile) {
                        formData.append('attachment', attachmentFile);
                    }

                    const response = await fetch("{{ route('osas.chat.send') }}", {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        const errData = await response.json().catch(() => ({}));
                        throw new Error(errData.message || errData.error || 'Failed to send message.');
                    }

                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to send message.');
                    }

                    // Update the temp message in place with the server copy.
                    const tempIndex = this.messages.findIndex(m => m.id === tempMessage.id);
                    if (tempIndex !== -1) {
                        Object.assign(this.messages[tempIndex], {
                            id: data.message.id,
                            created_at: data.message.created_at,
                            sender: data.message.sender,
                            attachment_path: data.message.attachment_path || null,
                            attachment_url: data.message.attachment_url || null,
                            is_image: data.message.is_image || false,
                            file_type: data.message.file_type || null
                        });
                    }
                    // Release the optimistic object URL now that the server URL
                    // is rendered in its place.
                    if (tempPreviewUrl) URL.revokeObjectURL(tempPreviewUrl);
                    this.pushToCache(this.selectedOrg, data.message);
                    this.lastId = data.message.id;
                    this.selectedOrg.last_message = data.message.message || (hasFile ? '[Attachment]' : '');
                    this.selectedOrg.last_message_from_osas = true;
                    this.selectedOrg.last_message_time = data.message.created_at;
                    this.$nextTick(() => this.scrollToBottom(true));
                } catch (error) {
                    // Remove the temp copy; only restore the typed text when the
                    // text send itself failed, so a failed attachment does not
                    // bring back text that was already delivered.
                    this.messages = this.messages.filter(m => m.id !== tempMessage.id);
                    if (text) {
                        this.messageText = text;
                    }
                    if (tempPreviewUrl) URL.revokeObjectURL(tempPreviewUrl);
                    window.showToast(error.message || 'Failed to send message.', 'error');
                }
            },

            async sendBroadcast() {
                const text = this.messageText.trim();
                const attachmentFile = this.broadcastAttachment;
                if ((!text && !attachmentFile) || this.sending || this.selectedOrgIds.length === 0) return;
                this.sending = true;
                try {
                    let data;
                    // When text and an attachment are combined, they are sent
                    // as two separate broadcasts (text first, then the
                    // attachment).
                    if (text && attachmentFile) {
                        await this.sendBroadcastSingle(text, null);
                        data = await this.sendBroadcastSingle('', attachmentFile);
                    } else {
                        data = await this.sendBroadcastSingle(text, attachmentFile);
                    }
                    if (data && data.success) {
                        window.showToast('Message sent to ' + (data.message.recipient_count || this.selectedOrgIds.length) + ' organizations.', 'success');
                        this.selectMode = false;
                        this.selectedOrgIds = [];
                        this.messageText = '';
                        this.loadOrgs(false);
                    }
                } catch (error) {
                    window.showToast(error.message || 'Failed to send broadcast.', 'error');
                } finally {
                    this.sending = false;
                }
            },

            async sendBroadcastSingle(text, attachmentFile) {
                const hasFile = !!attachmentFile;
                const formData = new FormData();
                formData.append('_token', this.csrfToken());
                formData.append('message', text);
                this.selectedOrgIds.forEach(id => formData.append('organization_ids[]', id));
                if (hasFile) {
                    formData.append('attachment', attachmentFile);
                }
                // Clear the composer immediately so the preview disappears
                // on send (same behaviour as the admin chat).
                this.clearBroadcastAttachment();

                const response = await fetch("{{ route('osas.chat.send') }}", {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    const errData = await response.json().catch(() => ({}));
                    throw new Error(errData.message || errData.error || 'Failed to send broadcast.');
                }

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to send broadcast.');
                }
                return data;
            },

            csrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.content : '';
            },

            toggleOrg(orgId) {
                const index = this.selectedOrgIds.indexOf(orgId);
                if (index === -1) {
                    this.selectedOrgIds.push(orgId);
                } else {
                    this.selectedOrgIds.splice(index, 1);
                }
            },

            toggleSelectAll() {
                this.selectedOrgIds = this.allSelected
                    ? []
                    : this.organizations.map(org => org.id);
            },

            handleRealtimeMessage(row) {
                if (this.open && this.view === 'chat' && this.selectedOrg && row.organization_id === this.selectedOrg.id) {
                    const exists = this.messages.some(msg => msg.id === row.id);
                    if (!exists && !this.hasPendingTwin(row)) {
                        this.messages.push(row);
                        this.pushToCache(this.selectedOrg, row);
                        this.lastId = Math.max(this.lastId, row.id);
                        this.$nextTick(() => this.scrollToBottom());
                    }
                    return;
                }

                if (row.sender_type === 'org') {
                    this.totalUnread += 1;
                }

                const org = this.organizations.find(o => o.id === row.organization_id);
                if (org) {
                    org.last_message = row.message || (row.attachment_path ? '[Attachment]' : '');
                    org.last_message_from_osas = row.sender_type === 'osas';
                    org.last_message_time = row.created_at;
                    this.pushToCache(org, row);
                }
            },

            scrollToBottom(force = false) {
                const container = document.getElementById('osas-floating-chat-messages');
                if (container && (force || this.isNearBottom(container))) container.scrollTop = container.scrollHeight;
            },

            isNearBottom(container) {
                if (!container) return true;
                return container.scrollHeight - container.scrollTop - container.clientHeight < 120;
            },

            attachThreadScrollListener() {
                const container = document.getElementById('osas-floating-chat-messages');
                if (!container) return;
                container.addEventListener('scroll', () => {
                    this.showThreadJumpLatest = !this.isNearBottom(container);
                });
            },

            jumpThreadToLatest() {
                const container = document.getElementById('osas-floating-chat-messages');
                if (container) container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            },

            formatTime(value) {
                if (!value) return '';
                const date = new Date(value);
                if (isNaN(date.getTime())) return '';
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },

            senderName(msg) {
                if (msg.sender) {
                    const first = (msg.sender.first_name || '').split(' ')[0];
                    const last = msg.sender.last_name || '';
                    return (first + ' ' + last).trim() || 'Organization Admin';
                }
                return 'Organization Admin';
            },

            pickThreadAttachment(event) {
                const file = event.target.files.length > 0 ? event.target.files[0] : null;
                if (!file) {
                    this.threadAttachment = null;
                    return;
                }
                if (file.type && file.type.indexOf('video/') === 0) {
                    event.target.value = '';
                    window.showToast && window.showToast('Video files are not allowed.', 'error');
                    this.threadAttachment = null;
                    return;
                }
                if (file.size > 20 * 1024 * 1024) {
                    event.target.value = '';
                    window.showToast && window.showToast('File is too large. Maximum size is 20MB.', 'error');
                    this.threadAttachment = null;
                    return;
                }
                if (this.threadPreviewUrl) URL.revokeObjectURL(this.threadPreviewUrl);
                this.threadPreviewUrl = file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : null;
                this.threadAttachment = file;
            },

            clearThreadAttachment() {
                if (this.threadPreviewUrl) {
                    URL.revokeObjectURL(this.threadPreviewUrl);
                    this.threadPreviewUrl = null;
                }
                this.threadAttachment = null;
                const input = document.getElementById('osas-thread-attachment-input');
                if (input) input.value = '';
            },

            pickBroadcastAttachment(event) {
                const file = event.target.files.length > 0 ? event.target.files[0] : null;
                if (!file) {
                    this.broadcastAttachment = null;
                    return;
                }
                if (file.type && file.type.indexOf('video/') === 0) {
                    event.target.value = '';
                    window.showToast && window.showToast('Video files are not allowed.', 'error');
                    this.broadcastAttachment = null;
                    return;
                }
                if (file.size > 20 * 1024 * 1024) {
                    event.target.value = '';
                    window.showToast && window.showToast('File is too large. Maximum size is 20MB.', 'error');
                    this.broadcastAttachment = null;
                    return;
                }
                if (this.broadcastPreviewUrl) URL.revokeObjectURL(this.broadcastPreviewUrl);
                this.broadcastPreviewUrl = file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : null;
                this.broadcastAttachment = file;
            },

            clearBroadcastAttachment() {
                if (this.broadcastPreviewUrl) {
                    URL.revokeObjectURL(this.broadcastPreviewUrl);
                    this.broadcastPreviewUrl = null;
                }
                this.broadcastAttachment = null;
                const input = document.getElementById('osas-broadcast-attachment-input');
                if (input) input.value = '';
            },

            flyAttachmentToChat(previewId) {
                const preview = document.getElementById(previewId);
                const container = document.getElementById('osas-floating-chat-messages');
                if (!preview || !container) return;
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                const from = preview.getBoundingClientRect();
                const to = container.getBoundingClientRect();
                const clone = preview.cloneNode(true);
                clone.style.cssText = `position:fixed;left:${from.left}px;top:${from.top}px;width:${from.width}px;height:${from.height}px;z-index:9999;transition:all .4s cubic-bezier(.2,.8,.2,1);border-radius:8px;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.18);`;
                document.body.appendChild(clone);
                requestAnimationFrame(() => {
                    clone.style.left = (to.left + to.width - 48) + 'px';
                    clone.style.top = (to.top + 12) + 'px';
                    clone.style.width = '44px';
                    clone.style.height = '44px';
                    clone.style.opacity = '.5';
                });
                setTimeout(() => clone.remove(), 420);
            },

            imageOnlyMessage(msg) {
                return !!(msg && msg.attachment_path && !(msg.message || '').trim()
                    && (msg.is_image || this.isImagePath(msg.attachment_path)));
            },

            shouldShowThreadTime(msg, index) {
                if (String(msg.id).startsWith('temp-')) return false;
                const next = this.messages[index + 1];
                if (!next) return true;
                if (next.sender_type !== msg.sender_type) return true;
                if (String(next.id).startsWith('temp-')) return true;
                return this.formatTime(next.created_at) !== this.formatTime(msg.created_at);
            },

            isImagePath(path) {
                const ext = this.pathExtension(path);
                return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(ext) !== -1;
            },

            pathExtension(path) {
                const parts = String(path || '').split('.');
                return parts.length > 1 ? parts.pop().toLowerCase() : '';
            },

            fileTypeLabel(path) {
                const ext = this.pathExtension(path).toUpperCase();
                const labels = {
                    PDF: 'PDF', DOC: 'DOC', DOCX: 'DOCX', XLS: 'XLS', XLSX: 'XLSX', CSV: 'CSV',
                    PPT: 'PPT', PPTX: 'PPTX', TXT: 'TXT', ZIP: 'ZIP', RAR: 'RAR'
                };
                return labels[ext] || (ext || 'FILE');
            },

            fileTypeColor(path) {
                const ext = this.pathExtension(path);
                const colors = {
                    pdf: '#e74c3c', doc: '#2b579a', docx: '#2b579a', xls: '#217346', xlsx: '#217346', csv: '#217346',
                    ppt: '#d24726', pptx: '#d24726', txt: '#6b7280', zip: '#f59e0b', rar: '#f59e0b'
                };
                return colors[ext] || '#9ca3af';
            },

            attachmentHtml(msg) {
                if (!msg || !msg.attachment_path) return '';
                const isTemp = String(msg.id).startsWith('temp-');
                const url = msg.attachment_url || (isTemp ? '#' : ('/osas/chat/' + msg.id + '/attachment'));

                if (msg.is_image || this.isImagePath(msg.attachment_path)) {
                    return `<a href="#" data-lightbox="${url}" class="block mt-2 max-w-[180px] rounded-xl overflow-hidden">
                        <img src="${url}" alt="Attachment" class="w-full h-auto block bg-white">
                    </a>`;
                }

                const label = this.fileTypeLabel(msg.attachment_path || msg.file_type);
                const color = this.fileTypeColor(msg.attachment_path || msg.file_type);
                const name = String(msg.attachment_name || 'Attachment')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                return `<a href="${url}" ${isTemp ? '' : 'download'} class="inline-flex items-center gap-2 mt-2 px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 hover:opacity-80 transition-all max-w-[220px]">
                    <span style="background:${color};color:#fff;font-weight:800;font-size:10px;padding:5px 8px;border-radius:6px;letter-spacing:.5px;flex-shrink:0;">${label}</span>
                    <span class="text-xs font-bold truncate">${name}</span>
                </a>`;
            },
        };
    }

    // Global bridge so any OSAS page (calendar modal, proposal view, ...)
    // can open the floating chat without a page navigation.
    window.openOsasChatWithOrg = function (orgId, fallback) {
        window.dispatchEvent(new CustomEvent('osas:open-chat', {
            detail: { orgId: orgId, fallback: fallback || null }
        }));
    };
    window.openOsasChatPanel = function () {
        window.dispatchEvent(new CustomEvent('osas:open-chat-panel'));
    };
</script>
