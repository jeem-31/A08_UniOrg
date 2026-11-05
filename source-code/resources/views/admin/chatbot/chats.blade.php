@extends('layouts.admin')

@section('title', 'Student Chats - Admin')

@push('styles')
<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes adminBounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3) translateY(20px);
        }
        50% {
            opacity: 1;
            transform: scale(1.05) translateY(-5px);
        }
        70% {
            transform: scale(0.9) translateY(2px);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    @keyframes adminSlideInBounce {
        0% {
            opacity: 0;
            transform: translateX(-50px) scale(0.8);
        }
        60% {
            opacity: 1;
            transform: translateX(10px) scale(1.1);
        }
        80% {
            transform: translateX(-5px) scale(0.95);
        }
        100% {
            transform: translateX(0) scale(1);
        }
    }
    
    .admin-message-student {
        animation: adminBounceIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .admin-message-admin {
        animation: adminSlideInBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .admin-message-animate {
        animation: adminBounceIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .no-animation {
        animation: none !important;
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="adminChatApp()">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Student Chats</h1>
        <p class="text-gray-600 mt-2">Respond to student inquiries</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Student List -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-indigo-600 text-white px-4 py-3">
                    <h2 class="font-bold">Active Conversations</h2>
                </div>
                <div class="divide-y divide-gray-200 max-h-[600px] overflow-y-auto">
                    <template x-for="student in students" :key="student.id">
                        <button @click="selectStudent(student)" 
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 transition"
                                :class="selectedStudent?.id === student.id ? 'bg-indigo-50' : ''">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <template x-if="student.role === 'osas_admin'">
                                        <div class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shadow-sm flex-shrink-0 mr-3">
                                            <img src="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png" alt="OSAS" class="w-6 h-6 object-contain">
                                        </div>
                                    </template>
                                    <template x-if="student.role !== 'osas_admin'">
                                        <i class="ph ph-user-circle text-2xl text-gray-400 mr-3"></i>
                                    </template>
                                    <div>
                                        <p class="font-semibold text-gray-900" x-text="student.name"></p>
                                        <p class="text-xs text-gray-500" x-text="student.last_message"></p>
                                    </div>
                                </div>
                                <span x-show="student.unread_count > 0" 
                                      class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full"
                                      x-text="student.unread_count"></span>
                            </div>
                        </button>
                    </template>
                    
                    <div x-show="students.length === 0" class="p-8 text-center text-gray-500">
                        <i class="ph ph-tray text-4xl mb-2 text-gray-300"></i>
                        <p>No active conversations</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden h-[600px] flex flex-col">
                <!-- Chat Header -->
                <div x-show="selectedStudent" class="bg-indigo-600 text-white px-6 py-4">
                    <div class="flex items-center">
                        <template x-if="selectedStudent?.role === 'osas_admin'">
                            <div class="w-10 h-10 rounded-lg bg-white/20 border border-white/30 flex items-center justify-center flex-shrink-0 mr-3">
                                <img src="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png" alt="OSAS" class="w-7 h-7 object-contain">
                            </div>
                        </template>
                        <template x-if="selectedStudent?.role !== 'osas_admin'">
                            <i class="ph ph-user-circle text-2xl mr-3"></i>
                        </template>
                        <div>
                            <p class="font-bold" x-text="selectedStudent?.name"></p>
                            <p class="text-sm text-indigo-100" x-text="selectedStudent?.email"></p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div id="admin-chat-messages" class="flex-1 overflow-y-auto p-6 bg-gray-50">
                    <template x-for="message in messages" :key="message.id">
                        <div class="mb-4" :class="message.sender_type === 'admin' ? 'text-right' : ''">
                            <div class="inline-block max-w-md px-4 py-2 rounded-lg shadow-md transition-all hover:shadow-lg transform hover:scale-105"
                                 :class="message.sender_type === 'admin' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-900'">
                                <p x-show="message.message" x-text="message.message"></p>
                                <a x-show="message.attachment_url && message.is_image" :href="message.attachment_url" target="_blank"
                                   class="block mt-2 max-w-[200px]">
                                    <img :src="message.attachment_url" :alt="message.attachment_name || 'Attachment'"
                                         class="w-full rounded-lg border border-black/10">
                                </a>
                                <a x-show="message.attachment_url && !message.is_image" :href="message.attachment_url" target="_blank"
                                   class="inline-flex items-center gap-2 mt-2 px-3 py-2 rounded-lg text-xs font-bold"
                                   :class="message.sender_type === 'admin' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">
                                    <i class="ph ph-paperclip"></i>
                                    <span x-text="message.attachment_name || 'Attachment'"></span>
                                </a>
                                <p class="text-xs mt-1 opacity-75">
                                    <span x-show="message.id && message.id.toString().startsWith('temp-')" class="italic">Sending...</span>
                                    <span x-show="!message.id || !message.id.toString().startsWith('temp-')" x-text="formatTime(message.created_at)"></span>
                                </p>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="!selectedStudent" class="text-center text-gray-500 py-16">
                        <i class="ph ph-chats text-6xl mb-4 text-gray-300"></i>
                        <p>Select a conversation to start chatting</p>
                    </div>
                </div>

                <!-- Input Form -->
                <form x-show="selectedStudent" @submit.prevent="sendMessage" data-no-loading class="p-4 bg-white border-t border-gray-200">
                    <div class="flex gap-2">
                        <input type="text" x-model="newMessage" placeholder="Type your reply..." required
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            <i class="ph ph-paper-plane-tilt"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function adminChatApp() {
    return {
        students: [],
        selectedStudent: null,
        messages: [],
        newMessage: '',
        
        init() {
            this.loadStudents();
            this.setupRealtimeListener();
            
            // Polling fallback (in case websocket is unavailable)
            setInterval(() => {
                this.loadStudents();
                if (this.selectedStudent) {
                    this.loadMessages(this.selectedStudent.id);
                }
            }, 2000); // Poll every 2 seconds
        },
        
        setupRealtimeListener() {
            if (typeof window.ChatRealtime === 'undefined' || typeof window.ChatRealtime.onMessage !== 'function') {
                // window.ChatRealtime may not exist yet if the app bundle is
                // still loading; retry until it becomes available.
                if (!window.__adminChatsRealtimeRetry) {
                    window.__adminChatsRealtimeRetry = true;
                    const retryTimer = setInterval(() => {
                        if (typeof window.ChatRealtime !== 'undefined' && typeof window.ChatRealtime.onMessage === 'function') {
                            clearInterval(retryTimer);
                            window.__adminChatsRealtimeRetry = false;
                            this.setupRealtimeListener();
                        }
                    }, 250);
                    setTimeout(() => { clearInterval(retryTimer); window.__adminChatsRealtimeRetry = false; }, 30000);
                }
                console.warn('ChatRealtime not available - admin chat updates will come from polling');
                return;
            }
            const currentOrgId = @js(session('current_organization_id'));
            window.ChatRealtime.onMessage((message) => {
                if (!message) return;
                
                // Only handle messages for this admin's organization.
                if (currentOrgId && String(message.organization_id) !== String(currentOrgId)) return;
                
                // Ignore our own sends: the optimistic bubble and the POST
                // response already handle them, so there is nothing to add.
                if (message.sender_type === 'admin' && String(message.admin_id) === String({{ auth()->id() }})) return;
                
                // Update the contact list preview instantly ("sabay" with the message).
                this.touchStudentPreview(message.user_id, message);
                
                // If the message belongs to the selected conversation, append it
                // directly from the realtime payload - instant, no server fetch.
                if (this.selectedStudent && String(this.selectedStudent.id) === String(message.user_id)) {
                    this.appendRealtimeMessage(message);
                    this.loadStudents();
                } else if (!this.selectedStudent && message.user_id) {
                    // No conversation open yet: select the student who just
                    // messaged so the new chat appears without a manual click.
                    this.loadStudents().then(() => {
                        const student = this.students.find(s => String(s.id) === String(message.user_id));
                        if (student) {
                            this.selectStudent(student);
                        }
                    });
                } else {
                    // A different conversation is open - just refresh the list
                    this.loadStudents();
                }
            });
        },
        
        appendRealtimeMessage(message) {
            // Skip messages already shown (dedupe by id)
            const alreadyExists = this.messages.some(m => String(m.id) === String(message.id));
            if (alreadyExists) return;
            
            // Skip if a pending optimistic copy matches this message (own send in flight)
            const hasPendingTwin = this.messages.some(m =>
                m.id && String(m.id).startsWith('temp-') &&
                m.sender_type === message.sender_type &&
                m.message === message.message
            );
            if (hasPendingTwin) return;
            
            this.messages.push(this.resolveAttachmentMeta(message));
            
            this.$nextTick(() => {
                const container = document.getElementById('admin-chat-messages');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },
        
        async loadStudents() {
            try {
                const response = await fetch('{{ route("admin.chatbot.students") }}');
                const data = await response.json();
                // Most recently active conversations first.
                this.students = (data.students || []).sort((a, b) =>
                    new Date(b.last_message_time || 0) - new Date(a.last_message_time || 0)
                );
            } catch (error) {
                console.error('Error loading students:', error);
            }
        },
        
        // Update the contact list preview instantly so the list and the
        // conversation panel stay in sync.
        touchStudentPreview(userId, message) {
            const student = this.students.find(s => String(s.id) === String(userId));
            if (!student || !message) return;
            const text = message.message || '';
            student.last_message = text.length > 30 ? text.slice(0, 30) + '...' : text;
            student.last_message_time = message.created_at || new Date().toISOString();
            if (message.sender_type === 'student') {
                student.unread_count = (student.unread_count || 0) + 1;
            }
            this.students = [...this.students].sort((a, b) =>
                new Date(b.last_message_time || 0) - new Date(a.last_message_time || 0)
            );
        },
        
        selectStudent(student) {
            this.selectedStudent = student;
            this.messages = [];
            this.loadMessages(student.id);
        },
        
        // Attach presentation meta (url / is_image / file_type) to a message
        // row coming from Supabase Realtime, which only carries the raw
        // attachment columns (no attachment_url or is_image flags).
        resolveAttachmentMeta(msg) {
            if (!msg || !msg.attachment_path || msg.attachment_url) return msg;
            const ext = String(msg.attachment_path.split('.').pop() || '').toLowerCase();
            msg.attachment_url = '/admin/chat/' + msg.id + '/attachment';
            msg.is_image = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
            msg.file_type = ext;
            return msg;
        },

        async loadMessages(userId) {
            try {
                const response = await fetch(`{{ url('admin/chatbot/messages') }}/${userId}`);
                const data = await response.json();
                
                // Merge instead of replace: never wipe messages already on
                // screen (e.g. an optimistic copy whose server response
                // hasn't arrived yet, or a stale response that raced with
                // the send). Only add messages we don't have, and skip
                // server copies that match a pending temp message.
                const serverMessages = data.messages || [];
                const currentIds = new Set(this.messages.map(m => String(m.id)));
                const newMessages = serverMessages.filter(m => {
                    if (currentIds.has(String(m.id))) return false;
                    return !this.messages.some(t =>
                        t.id && String(t.id).startsWith('temp-') &&
                        t.sender_type === m.sender_type &&
                        t.message === m.message
                    );
                });
                if (newMessages.length > 0) {
                    this.messages = [...this.messages, ...newMessages]
                        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                }
                
                this.$nextTick(() => {
                    const container = document.getElementById('admin-chat-messages');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        },
        
        async sendMessage() {
            if (!this.newMessage.trim() || !this.selectedStudent) return;
            
            const messageText = this.newMessage;
            this.newMessage = ''; // Clear input immediately
            
            // Add message to UI immediately (optimistic update)
            const tempMessage = {
                id: 'temp-' + Date.now(),
                message: messageText,
                sender_type: 'admin',
                created_at: new Date().toISOString(),
                admin_id: {{ auth()->id() }}
            };
            this.messages.push(tempMessage);
            
            // Scroll to bottom immediately
            this.$nextTick(() => {
                const container = document.getElementById('admin-chat-messages');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
            
            try {
                const response = await fetch('{{ route("admin.chatbot.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ 
                        user_id: this.selectedStudent.id,
                        message: messageText 
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Update temp message properties without replacing the object
                    const tempIndex = this.messages.findIndex(m => m.id === tempMessage.id);
                    if (tempIndex !== -1) {
                        // Update properties in place to avoid re-rendering
                        Object.assign(this.messages[tempIndex], {
                            id: data.message.id,
                            created_at: data.message.created_at,
                            admin_id: data.message.admin_id
                        });
                    }
                    // Update the contact list preview instantly.
                    this.touchStudentPreview(this.selectedStudent.id, {
                        message: messageText,
                        created_at: data.message.created_at,
                        sender_type: 'admin'
                    });
                } else {
                    // Remove temp message on error
                    this.messages = this.messages.filter(m => m.id !== tempMessage.id);
                    this.newMessage = messageText; // Restore message on error
                    alert('Failed to send message. Please try again.');
                }
            } catch (error) {
                console.error('Error sending admin message:', error);
                // Remove temp message on error
                this.messages = this.messages.filter(m => m.id !== tempMessage.id);
                this.newMessage = messageText; // Restore message on error
                alert('Failed to send message. Please try again.');
            }
        },
        
        formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
    }
}
</script>
@endsection
