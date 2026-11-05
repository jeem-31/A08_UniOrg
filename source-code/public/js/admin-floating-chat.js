// Admin Floating Chat Widget Functionality
(function() {
    'use strict';

    // State management
    let currentStudentId = null;
    let students = [];
    let searchTimeout = null;
    let isUserTyping = false;
    let typingTimeout = null;
    // Which student the conversation panel is currently showing. Drives
    // messages-only refreshes so the input area is never rebuilt (and the
    // admin's draft text never lost) while the panel is being polled.
    let renderedStudentId = null;
    // OSAS thread history pagination: the newest 50 messages load first, and
    // older batches are fetched on demand when "Load earlier messages" is hit.
    let osasHasMore = false;
    let osasOldestId = 0;
    let loadingEarlier = false;

    // DOM Elements
    let adminChatWidget = null;
    let adminChatButton = null;
    let adminChatWindow = null;
    let closeAdminChat = null;
    let adminChatContacts = null;
    let adminChatRightPanel = null;
    let adminChatWelcome = null;
    
    // Store event listeners for cleanup
    let eventListeners = [];

    // Initialize admin chat
    function initAdminChat() {
        // Only initialize if admin chat widget exists (admin pages only)
        adminChatWidget = document.getElementById('admin-chat-widget');
        if (!adminChatWidget) {
            console.log('Admin chat widget not found - skipping initialization');
            return;
        }
        
        // Clean up old event listeners
        cleanupEventListeners();
        
        // Re-query DOM elements
        adminChatButton = document.getElementById('admin-chat-button');
        adminChatWindow = document.getElementById('admin-chat-window');
        closeAdminChat = document.getElementById('close-admin-chat');
        adminChatContacts = document.querySelector('.admin-chat-contacts');
        adminChatRightPanel = document.querySelector('.admin-chat-right-panel');
        adminChatWelcome = document.querySelector('.admin-chat-welcome');
        
        if (!adminChatButton || !adminChatWindow) {
            console.error('Admin chat elements not found');
            return;
        }

        // Setup websocket listener for real-time badge updates
        setupWebsocketListener();

        // Initial badge count load
        updateAdminBadgeCount();

        // "Jump to latest" button: scroll events do not bubble, so listen in
        // the capture phase and match the (recreated) messages container by id.
        // This keeps the button in sync without re-attaching per render.
        const jumpScrollHandler = function(e) {
            if (e.target && e.target.id === 'adminConversationMessages') {
                const btn = document.getElementById('adminJumpLatestBtn');
                if (!btn) return;
                const nearBottom = e.target.scrollHeight - e.target.scrollTop - e.target.clientHeight < 200;
                btn.style.display = nearBottom ? 'none' : 'flex';
            }
        };
        document.addEventListener('scroll', jumpScrollHandler, true);
        eventListeners.push({ element: document, event: 'scroll', handler: jumpScrollHandler, capture: true });

        const jumpClickHandler = function(e) {
            const btn = e.target && e.target.closest ? e.target.closest('#adminJumpLatestBtn') : null;
            if (!btn) return;
            const container = document.getElementById('adminConversationMessages');
            if (container) {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            }
        };
        document.addEventListener('click', jumpClickHandler);
        eventListeners.push({ element: document, event: 'click', handler: jumpClickHandler });

        // Toggle chat window
        const toggleHandler = function(e) {
            e.stopPropagation();
            toggleAdminChatWindow();
        };
        adminChatButton.addEventListener('click', toggleHandler);
        eventListeners.push({ element: adminChatButton, event: 'click', handler: toggleHandler });

        // Close chat
        if (closeAdminChat) {
            const closeHandler = function(e) {
                e.stopPropagation();
                closeAdminChatWindow();
            };
            closeAdminChat.addEventListener('click', closeHandler);
            eventListeners.push({ element: closeAdminChat, event: 'click', handler: closeHandler });
        }

        // Close chat when clicking outside
        const outsideClickHandler = function(e) {
            // Keep the chat open while interacting with the image lightbox
            // (it is appended to body, outside the widget).
            if (e.target.closest('#admin-lightbox')) return;
            if (adminChatWindow.classList.contains('admin-chat-window-visible') && 
                !adminChatWidget.contains(e.target)) {
                closeAdminChatWindow();
            }
        };
        document.addEventListener('click', outsideClickHandler);
        eventListeners.push({ element: document, event: 'click', handler: outsideClickHandler });

        // Prevent chat window clicks from closing it - but let clicks on
        // image attachments keep bubbling so the delegated lightbox handler
        // on document can open the zoom modal.
        const preventCloseHandler = function(e) {
            if (e.target.closest('[data-lightbox]')) return;
            e.stopPropagation();
        };
        adminChatWindow.addEventListener('click', preventCloseHandler);
        eventListeners.push({ element: adminChatWindow, event: 'click', handler: preventCloseHandler });

        // Load students when chat is opened
        loadStudents();
    }
    
    // The Supabase Realtime websocket streams chat messages straight from the
    // database, so no HTTP polling is needed. The realtime listener in
    // setupWebsocketListener() keeps the badge, contact list and open
    // conversation fresh, and the badge count is also fetched on initial load.
    
    // Clean up event listeners
    function cleanupEventListeners() {
        eventListeners.forEach(({ element, event, handler, capture }) => {
            element.removeEventListener(event, handler, capture || false);
        });
        eventListeners = [];
    }
    
    // Store current admin badge count and visibility to avoid unnecessary updates
    let currentAdminBadgeCount = null;
    let isAdminBadgeVisible = false;
    
    // Setup realtime listener for chat updates (Supabase Realtime streams the
    // inserted admin_chats row straight from the database).
    function setupWebsocketListener() {
        // window.ChatRealtime may not exist yet if the app bundle is still
        // loading, so retry until it becomes available instead of giving up.
        if (typeof window.ChatRealtime === 'undefined' || typeof window.ChatRealtime.onMessage !== 'function') {
            if (!window.__adminChatRealtimeRetry) {
                window.__adminChatRealtimeRetry = true;
                const retryTimer = setInterval(() => {
                    if (typeof window.ChatRealtime !== 'undefined' && typeof window.ChatRealtime.onMessage === 'function') {
                        clearInterval(retryTimer);
                        window.__adminChatRealtimeRetry = false;
                        setupWebsocketListener();
                    }
                }, 250);
                setTimeout(() => { clearInterval(retryTimer); window.__adminChatRealtimeRetry = false; }, 30000);
            }
            return;
        }
        console.log('Setting up Supabase Realtime listener for admin chat...');

        window.ChatRealtime.onMessage((message) => {
            if (!message) return;

            // Only handle messages for this admin's organization.
            const orgId = adminChatWidget ? adminChatWidget.dataset.orgId : null;
            if (orgId && String(message.organization_id) !== String(orgId)) return;

            console.log('Received chat message via Supabase Realtime:', message);

            // ===== OSAS channel: OSAS office sent a message =====
            if (message.channel === 'osas' && message.sender_type === 'osas') {
                updateAdminBadgeCount();

                // Update the contact list preview instantly.
                touchOsasPreview(message);

                if (adminChatWindow && adminChatWindow.classList.contains('admin-chat-window-visible')) {
                    if (currentStudentId && String(currentStudentId) === 'osas') {
                        // OSAS thread is open: append the message directly.
                        console.log('Appending OSAS message to open thread');
                        appendMessageBubble(message, 'osas');
                        loadStudents();
                    } else if (!currentStudentId) {
                        // No conversation open yet: open the OSAS thread so the
                        // new message appears without needing a manual click.
                        loadStudents().then(() => {
                            if (students.some(s => String(s.id) === 'osas')) {
                                console.log('Auto-opening OSAS conversation');
                                openStudentChat('osas');
                            }
                        });
                    } else {
                        // A different conversation is open - refresh the list.
                        loadStudents();
                    }
                }
                return;
            }

            // ===== OSAS channel: this admin's own message to OSAS =====
            if (message.channel === 'osas' && message.sender_type === 'org'
                && adminChatWidget && String(message.user_id) === String(adminChatWidget.dataset.adminId)) {
                touchOsasPreview(message);

                if (currentStudentId && String(currentStudentId) === 'osas') {
                    console.log('OSAS message sent via realtime, updating UI');
                    const messagesContainer = document.getElementById('adminConversationMessages');
                    if (messagesContainer) {
                        const tempElement = findPendingBubble(messagesContainer, message);
                        if (tempElement) {
                            const timeElement = tempElement.querySelector('.admin-message-time');
                            if (timeElement) {
                                timeElement.textContent = formatTime(message.created_at);
                            }
                            tempElement.classList.add('message-sent');
                            tempElement.setAttribute('data-message-id', message.id);
                        }
                    }
                }
                return;
            }

            // Student sent a message.
            if (message.sender_type === 'student') {
                // The realtime payload has no aggregate count - fetch it.
                updateAdminBadgeCount();

                // Update the contact list preview instantly so the list and
                // the conversation panel stay in sync.
                touchStudentPreview(message.user_id, message);

                // Reload students list if chat is open
                if (adminChatWindow && adminChatWindow.classList.contains('admin-chat-window-visible')) {
                    if (currentStudentId && message.user_id == currentStudentId) {
                        // Viewing this student's conversation: append the
                        // message directly from the realtime payload - instant
                        // display without waiting for a server fetch.
                        console.log('Appending incoming message for current student');
                        appendMessageBubble(message);
                        // Refresh the contact list in the background only
                        loadStudents();
                    } else if (!currentStudentId) {
                        // No conversation open yet: open the student who just
                        // messaged so the new chat appears without needing a
                        // manual click on the contact list.
                        loadStudents().then(() => {
                            const found = students.some(s => s.id == message.user_id);
                            if (found) {
                                console.log('Auto-opening conversation for student', message.user_id);
                                openStudentChat(message.user_id);
                            }
                        });
                    } else {
                        // A different conversation is open - just refresh the
                        // contact list so its badge updates.
                        loadStudents();
                    }
                }
            }

            // This admin's own sent message: flip the optimistic bubble.
            const myAdminId = adminChatWidget ? adminChatWidget.dataset.adminId : null;
            if (message.sender_type === 'admin' && myAdminId && String(message.admin_id) === String(myAdminId)) {
                // Update the contact list preview instantly as well.
                touchStudentPreview(message.user_id, message);

                if (currentStudentId && message.user_id == currentStudentId) {
                    console.log('Admin message sent via realtime, updating UI');
                    // The message was already added optimistically, just mark it as sent
                    const messagesContainer = document.getElementById('adminConversationMessages');
                    if (messagesContainer) {
                        const tempElement = findPendingBubble(messagesContainer, message);
                        if (tempElement) {
                            const timeElement = tempElement.querySelector('.admin-message-time');
                            if (timeElement) {
                                timeElement.textContent = formatTime(message.created_at);
                            }
                            tempElement.classList.add('message-sent');
                            tempElement.setAttribute('data-message-id', message.id);
                        }
                    }
                }
            }
        });
    }
    
    // Update badge display (extracted for reuse)
    function updateBadgeDisplay(newCount) {
        const badge = document.getElementById('admin-chat-badge');
        if (badge) {
            const shouldBeVisible = newCount > 0;
            
            // Only update DOM if something actually changed
            if (newCount !== currentAdminBadgeCount || shouldBeVisible !== isAdminBadgeVisible) {
                console.log('Badge count updated:', currentAdminBadgeCount, '->', newCount);
                
                currentAdminBadgeCount = newCount;
                isAdminBadgeVisible = shouldBeVisible;
                
                if (shouldBeVisible) {
                    badge.textContent = newCount;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }
    }
    
    // Update admin badge count (now only used for initial load)
    async function updateAdminBadgeCount() {
        try {
            const response = await fetch('/admin/chat/unread-count');
            const data = await response.json();
            updateBadgeDisplay(data.count);
        } catch (error) {
            console.error('Error fetching admin unread count:', error);
        }
    }

    function toggleAdminChatWindow() {
        const isVisible = adminChatWindow.classList.contains('admin-chat-window-visible');
        
        if (isVisible) {
            closeAdminChatWindow();
        } else {
            openAdminChatWindow();
        }
    }

    function openAdminChatWindow() {
        console.log('openAdminChatWindow called');
        console.log('adminChatWindow:', adminChatWindow);
        console.log('adminChatWindow classes before:', adminChatWindow?.className);
        
        adminChatWindow.classList.remove('admin-chat-window-hidden');
        adminChatWindow.classList.add('admin-chat-window-visible');
        adminChatButton.classList.add('chat-opened');
        
        console.log('adminChatWindow classes after:', adminChatWindow?.className);
        
        // Show the already-loaded conversations immediately (the list is
        // populated on page load) and refresh it in the background. Never
        // clear the list first, otherwise the panel looks blank every open.
        renderStudents();
        loadStudents().then(() => {
            // If no conversation is open yet, auto-open the most recently
            // active one so the chat panel is never left empty.
            if (!currentStudentId && students.length > 0) {
                const sorted = [...students].sort((a, b) =>
                    new Date(b.last_message_time || 0) - new Date(a.last_message_time || 0)
                );
                console.log('Auto-opening most recent conversation:', sorted[0].id);
                openStudentChat(sorted[0].id);
            }
        });
        
        // Update badge immediately when opening chat
        updateAdminBadgeCount();
        
        // NO MORE POLLING - websocket handles real-time updates!
        // Removed setInterval for polling
    }

    function closeAdminChatWindow() {
        adminChatWindow.classList.remove('admin-chat-window-visible');
        adminChatWindow.classList.add('admin-chat-window-hidden');
        adminChatButton.classList.remove('chat-opened');
        
        // No more polling to clean up - websocket handles everything!
    }

    // Load all students with conversations
    async function loadStudents() {
        try {
            console.log('Loading students from /admin/chatbot/students...');
            const response = await fetch('/admin/chatbot/students?_=' + Date.now(), {
                cache: 'no-store',
                headers: { 'Cache-Control': 'no-cache' }
            });
            const data = await response.json();

            console.log('Response status:', response.status);
            console.log('Response data:', data);

            if (response.ok) {
                students = data.students || [];
                console.log('Loaded students:', students);
                renderStudents();
            } else {
                console.error('Error loading students:', data.error);
                renderStudents(); // Still render to show empty state
            }
        } catch (error) {
            console.error('Error loading students:', error);
            renderStudents(); // Still render to show empty state
        }
    }

    // Render student list
    function renderStudents() {
        if (!adminChatContacts) {
            console.error('Admin chat contacts container not found');
            return;
        }

        let html = '';

        if (students.length === 0) {
            html = `
                <div style="padding: 40px 20px; text-align: center; color: #9ca3af; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.5; margin-bottom: 16px;">
                        <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="currentColor"/>
                    </svg>
                    <p style="margin: 0; font-size: 14px;">No student is chatting</p>
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #d1d5db;">Messages will appear when students contact you</p>
                </div>
            `;
        } else {
            students.forEach(student => {
                const avatar = generateStudentAvatar(student.name, student.photo, student.role, student.email);
                const time = formatTime(student.last_message_time);
                const unreadBadge = student.unread_count > 0 ? 
                    `<span class="admin-unread-badge">${student.unread_count}</span>` : '';
                
                html += `
                    <div class="admin-chat-contact${currentStudentId == student.id ? ' active' : ''}" data-student-id="${student.id}">
                        <div class="admin-contact-avatar">
                            ${avatar}
                        </div>
                        <div class="admin-contact-info">
                            <div class="admin-contact-name">${escapeHtml(student.name)}</div>
                            <div class="admin-contact-message">${escapeHtml(student.last_message)}</div>
                        </div>
                        <div class="admin-contact-time">${time}${unreadBadge}</div>
                    </div>
                `;
            });
        }

        adminChatContacts.innerHTML = html;

        // Add click handlers to student contacts
        const contactElements = adminChatContacts.querySelectorAll('.admin-chat-contact');
        contactElements.forEach(contact => {
            contact.addEventListener('click', function() {
                const studentId = this.dataset.studentId;
                openStudentChat(studentId);
            });
        });
    }

    // Open chat with specific student
    async function openStudentChat(studentId) {
        currentStudentId = studentId;
        
        // Update active state
        const contactElements = adminChatContacts.querySelectorAll('.admin-chat-contact');
        contactElements.forEach(contact => {
            contact.classList.remove('active');
            if (contact.dataset.studentId == studentId) {
                contact.classList.add('active');
            }
        });

        // Hide welcome message
        if (adminChatWelcome) {
            adminChatWelcome.classList.add('hidden');
        }
        
        // Show right panel
        if (adminChatRightPanel) {
            adminChatRightPanel.classList.remove('hidden');
        }

        const student = students.find(s => s.id == studentId);

        // OSAS thread history state comes from the students payload (newest
        // 50 messages + has_more flag), refreshed by loadStudentMessages().
        if (student && String(studentId) === 'osas') {
            osasHasMore = !!(student.has_more || false);
            osasOldestId = student.oldest_id || 0;
        }

        // Clear the unread badge for this conversation instantly so the list
        // reflects the open conversation without waiting on a server round trip.
        if (student && student.unread_count > 0) {
            student.unread_count = 0;
            renderStudents();
        }

        // Render the conversation IMMEDIATELY from the message history already
        // bundled in the students payload (cached on every list refresh), then
        // refresh it in the background so nothing is ever stale. No blank
        // panel, no waiting on a fetch.
        if (student && student.messages) {
            // Keep any in-flight optimistic sends visible while the
            // background refresh is on its way (mirrors mergeConversationMessages).
            const pending = Object.values(pendingSends).filter(ps => String(ps.user_id) === String(studentId));
            const cachedMessages = pending.length
                ? [...student.messages, ...pending.map(ps => ({
                    id: ps.key,
                    message: ps.message,
                    sender_type: ps.sender_type || 'admin',
                    created_at: ps.created_at,
                    is_temp: !ps.realId
                }))]
                : student.messages;

            renderStudentChatUI(student, cachedMessages, '', true);
            loadStudentMessages(studentId);
        } else {
            // No cached history yet (e.g. very first load before the payload
            // arrived) - fall back to fetching.
            await loadStudentMessages(studentId);
        }
    }

    // Tracks which conversation is currently being fetched so the poll never
    // stacks overlapping requests for the same student.
    let loadingConversationFor = null;

    // Source of truth for messages the admin has sent. The conversation panel
    // is re-rendered from server responses, so this guarantees a just-sent
    // message can never be dropped by a stale refresh - it stays visible until
    // the server response confirms it.
    let pendingSends = {};

    // Build the rendered message list from three sources: the server response,
    // the messages currently on screen (optimistic copies), and pending sends.
    // Dedupe by id so a message always appears exactly once.
    function mergeConversationMessages(serverMessages, displayedMessages) {
        const serverIds = new Set(serverMessages.map(m => String(m.id)));

        // Capture the pending temp/real ids BEFORE confirming anything so a
        // displayed temp copy can still be matched to its (about to be
        // confirmed) send below.
        const pendingByRealId = new Set(
            Object.values(pendingSends).filter(ps => ps.realId).map(ps => String(ps.realId))
        );
        const pendingTempIds = new Set(
            Object.values(pendingSends).map(ps => String(ps.key))
        );

        // Drop pending sends that the server has already confirmed.
        Object.keys(pendingSends).forEach(key => {
            const ps = pendingSends[key];
            if (ps.realId && serverIds.has(String(ps.realId))) delete pendingSends[key];
        });

        const seen = new Set();
        const result = [];
        const add = (m) => {
            const key = String(m.id);
            if (seen.has(key)) return;
            seen.add(key);
            result.push(m);
        };

        serverMessages.forEach(add);

        displayedMessages.forEach(m => {
            if (serverIds.has(String(m.id))) return;
            if (pendingByRealId.has(String(m.id))) return; // pending copy below handles it
            if (pendingTempIds.has(String(m.id))) return; // pending copy below handles it
            add(m);
        });

        Object.values(pendingSends).forEach(ps => {
            if (ps.realId && serverIds.has(String(ps.realId))) return;
            add({ id: ps.key, message: ps.message, sender_type: ps.sender_type || 'admin', created_at: ps.created_at, is_temp: !ps.realId });
        });

        return result;
    }

    // Update the contact list preview instantly from a message (event or send)
    // so the list and the conversation panel stay in sync ("sabay").
    function touchStudentPreview(userId, message) {
        const student = students.find(s => s.id == userId);
        if (!student || !message) return;
        const text = message.message || (message.attachment_path ? '[Attachment]' : '');
        student.last_message = text.length > 30 ? text.slice(0, 30) + '...' : text;
        student.last_message_time = message.created_at || new Date().toISOString();
        if (message.sender_type === 'student') {
            student.unread_count = (student.unread_count || 0) + 1;
        }
        // Most recently active conversation moves to the top of the list.
        students = [...students].sort((a, b) =>
            new Date(b.last_message_time || 0) - new Date(a.last_message_time || 0)
        );
        renderStudents();
    }

    // Update the OSAS thread preview in the contact list instantly (the
    // synthetic 'osas' contact rendered from the students payload).
    function touchOsasPreview(message) {
        const contact = students.find(s => String(s.id) === 'osas');
        if (!contact || !message) return;
        const text = message.message || (message.attachment_path ? '[Attachment]' : '');
        contact.last_message = text.length > 30 ? text.slice(0, 30) + '...' : text;
        contact.last_message_time = message.created_at || new Date().toISOString();
        if (message.sender_type === 'osas') {
            contact.unread_count = (contact.unread_count || 0) + 1;
        }
        students = [...students].sort((a, b) =>
            new Date(b.last_message_time || 0) - new Date(a.last_message_time || 0)
        );
        renderStudents();
    }

    // Load messages for a specific student
    async function loadStudentMessages(studentId, preserveScroll = false) {
        if (loadingConversationFor === studentId) return;
        loadingConversationFor = studentId;
        try {
            // Get current input value before reloading
            const messageInput = document.getElementById('adminMessageInput');
            const currentInputValue = messageInput ? messageInput.value : '';

            // Decide the scroll behaviour before the panel is replaced: keep the
            // current position on background refreshes (unless already near the
            // bottom) and jump to the bottom on explicit opens.
            const previousContainer = document.getElementById('adminConversationMessages');
            const stickToBottom = !preserveScroll || !previousContainer
                || (previousContainer.scrollHeight - previousContainer.scrollTop - previousContainer.clientHeight) < 200;
            const previousScrollTop = previousContainer ? previousContainer.scrollTop : 0;
            
            // Capture every message currently on screen so a just-sent message
            // (or one still awaiting its server response) is never lost when
            // the conversation is re-rendered from a server response.
            const messagesContainer = document.getElementById('adminConversationMessages');
            let displayedMessages = [];
            if (messagesContainer) {
                const messageElements = messagesContainer.querySelectorAll('.admin-message');
                messageElements.forEach((element, index) => {
                    const contentElement = element.querySelector('.admin-message-content p');
                    const timeElement = element.querySelector('.admin-message-time');
                    if (!contentElement) return;
                    displayedMessages.push({
                        id: element.getAttribute('data-message-id') || 'temp-' + Date.now() + '-' + index,
                        message: contentElement.textContent,
                        sender_type: element.classList.contains('sent') ? 'admin' : 'student',
                        created_at: new Date().toISOString(),
                        is_temp: !!(timeElement && timeElement.textContent === 'Sending...')
                    });
                });
            }
            
            const response = await fetch(`/admin/chatbot/messages/${studentId}`);
            
            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.error('Received HTML instead of JSON. User might not be authenticated or route not found.');
                console.log('Response status:', response.status);
                console.log('Response URL:', response.url);
                return;
            }
            
            const data = await response.json();

            // The admin may have switched conversations while this request was
            // in flight - never let a stale response clobber the open one.
            if (String(studentId) !== String(currentStudentId)) return;

            if (response.ok) {
                const serverMessages = data.messages || [];

                // OSAS thread: keep the history-pagination state in sync with
                // what the server actually shipped.
                if (String(studentId) === 'osas') {
                    osasHasMore = !!(data.has_more || false);
                    osasOldestId = data.oldest_id || 0;
                    const wrap = document.getElementById('adminLoadEarlierWrap');
                    if (wrap) wrap.style.display = osasHasMore ? 'flex' : 'none';
                }

                const student = students.find(s => s.id == studentId);
                
                // Combine server messages with the messages on screen and any
                // pending sends (deduped) so a just-sent message is never
                // dropped by a stale refresh.
                const allMessages = mergeConversationMessages(serverMessages, displayedMessages);
                
                renderStudentChatUI(student, allMessages, currentInputValue, stickToBottom, previousScrollTop);
            } else {
                console.error('Error loading messages:', data.error);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        } finally {
            loadingConversationFor = null;
        }
    }

    // Render student chat UI
    function renderStudentChatUI(student, messages, preservedInputValue = '', stickToBottom = true, previousScrollTop = 0) {
        if (!adminChatRightPanel) return;

        // Background refresh for the conversation already on screen: swap only
        // the messages container. The header and the input are left untouched,
        // so the admin's draft text, focus and caret survive every poll.
        const existingContainer = document.getElementById('adminConversationMessages');
        if (existingContainer && renderedStudentId !== null && renderedStudentId == student.id) {
            existingContainer.innerHTML = renderAdminMessages(messages, student.channel);
            existingContainer.scrollTop = stickToBottom ? existingContainer.scrollHeight : previousScrollTop;
            return;
        }

        const avatar = generateStudentAvatar(student.name, student.photo, student.role, student.email);
        
        const html = `
            <div class="admin-chat-conversation active">
                <div class="admin-conversation-header">
                    <div class="admin-conversation-contact">
                        <div class="admin-conversation-avatar">
                            ${avatar}
                        </div>
                        <div>
                            <div class="admin-conversation-name">${escapeHtml(student.name)}</div>
                            <div class="admin-conversation-status">${escapeHtml(student.email)}</div>
                        </div>
                    </div>
                </div>
                <div class="admin-conversation-messages-wrap">
                    <div class="admin-load-earlier-wrap" id="adminLoadEarlierWrap" style="display:${student.channel === 'osas' && (osasHasMore || (student.has_more || false)) ? 'flex' : 'none'};">
                        <button type="button" id="adminLoadEarlierBtn" class="admin-load-earlier-btn">Load earlier messages</button>
                    </div>
                    <div class="admin-conversation-messages" id="adminConversationMessages">
                        ${renderAdminMessages(messages, student.channel)}
                    </div>
                    <button type="button" id="adminJumpLatestBtn" class="admin-jump-latest-btn" style="display:none;" title="Jump to latest messages" aria-label="Jump to latest messages">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5v14M19 12l-7 7-7-7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="admin-conversation-input">
                    <div id="adminAttachmentPreview" class="admin-attachment-preview hidden">
                        <div class="admin-attachment-preview-inner">
                            <img id="adminAttachmentThumb" class="admin-attachment-thumb hidden" alt="Attachment preview">
                            <div id="adminAttachmentTile" class="admin-attachment-tile hidden"><span id="adminAttachmentTileLabel"></span></div>
                            <button type="button" id="adminAttachmentClear" class="admin-attachment-clear" title="Remove attachment">&times;</button>
                        </div>
                    </div>
                    <div class="admin-conversation-input-row">
                        <label class="admin-attach-button" title="Attach a file">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21.44 11.05L12.25 20.24C11.1242 21.3658 9.59723 21.9983 8.005 21.9983C6.41277 21.9983 4.88584 21.3658 3.76 20.24C2.63416 19.1142 2.00166 17.5872 2.00166 15.995C2.00166 14.4028 2.63416 12.8758 3.76 11.75L12.33 3.18C13.0806 2.42935 14.0822 2.00648 15.1275 2.00648C16.1728 2.00648 17.1744 2.42935 17.925 3.18C18.6756 3.93065 19.0985 4.93225 19.0985 5.9775C19.0985 7.02275 18.6756 8.02435 17.925 8.775L9.1 17.6C8.72489 17.9751 8.21834 18.1857 7.69 18.1857C7.16166 18.1857 6.65511 17.9751 6.28 17.6C5.90489 17.2249 5.69426 16.7183 5.69426 16.19C5.69426 15.6617 5.90489 15.1551 6.28 14.78L14.55 6.51" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input type="file" id="adminAttachmentInput" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="hidden">
                        </label>
                        <input type="text" class="admin-message-input" placeholder="Type your response..." id="adminMessageInput">
                        <button class="admin-send-button" id="adminSendButton">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;

        adminChatRightPanel.innerHTML = html;
        renderedStudentId = student.id;

        // Keep the current scroll position on background refreshes so polling
        // never yanks the view while the admin is reading history.
        const messagesContainer = document.getElementById('adminConversationMessages');
        if (messagesContainer) {
            messagesContainer.scrollTop = stickToBottom ? messagesContainer.scrollHeight : previousScrollTop;
        }

        // Add send message handler
        const messageInput = document.getElementById('adminMessageInput');
        const sendButton = document.getElementById('adminSendButton');

        if (sendButton) {
            sendButton.addEventListener('click', sendAdminMessage);
        }

        if (messageInput) {
            // Restore the preserved input value
            if (preservedInputValue) {
                messageInput.value = preservedInputValue;
            }
            
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendAdminMessage();
                }
            });
            
            // Add typing detection
            messageInput.addEventListener('input', function() {
                isUserTyping = true;
                
                // Clear existing timeout
                if (typingTimeout) {
                    clearTimeout(typingTimeout);
                }
                
                // Set user as not typing after 3 seconds of inactivity
                typingTimeout = setTimeout(() => {
                    isUserTyping = false;
                }, 3000);
            });
            
            messageInput.focus();
        }

        // Attachment picker (OSAS threads only): show an inline preview above
        // the text area (image thumbnail or a file-type tile) with a remove
        // button. Video files are rejected outright.
        const attachmentInput = document.getElementById('adminAttachmentInput');
        const attachmentPreview = document.getElementById('adminAttachmentPreview');
        const attachmentThumb = document.getElementById('adminAttachmentThumb');
        const attachmentTile = document.getElementById('adminAttachmentTile');
        const attachmentTileLabel = document.getElementById('adminAttachmentTileLabel');
        const attachmentClear = document.getElementById('adminAttachmentClear');

        const clearAttachmentPreview = function() {
            if (!attachmentPreview) return;
            attachmentPreview.classList.add('hidden');
            attachmentThumb.classList.add('hidden');
            attachmentTile.classList.add('hidden');
            if (attachmentThumb.src) URL.revokeObjectURL(attachmentThumb.src);
            attachmentThumb.removeAttribute('src');
        };

        if (attachmentInput) {
            attachmentInput.addEventListener('change', function() {
                clearAttachmentPreview();
                const file = this.files.length > 0 ? this.files[0] : null;
                if (!file) return;

                if (file.type && file.type.indexOf('video/') === 0) {
                    this.value = '';
                    window.showToast && window.showToast('Video files are not allowed.', 'error');
                    return;
                }

                if (file.size > MAX_ATTACHMENT_BYTES) {
                    this.value = '';
                    window.showToast && window.showToast('File is too large. Maximum size is 20MB.', 'error');
                    return;
                }

                if (file.type && file.type.indexOf('image/') === 0) {
                    attachmentThumb.src = URL.createObjectURL(file);
                    attachmentThumb.classList.remove('hidden');
                } else {
                    attachmentTileLabel.textContent = fileTypeLabel(file.name);
                    attachmentTile.classList.remove('hidden');
                }
                attachmentPreview.classList.remove('hidden');
            });
        }

        if (attachmentClear) {
            attachmentClear.addEventListener('click', function() {
                clearAttachmentPreview();
                if (attachmentInput) attachmentInput.value = '';
            });
        }

        // Load earlier OSAS messages (only shown when the thread has more
        // history than the initial 50-message batch).
        const loadEarlierBtn = document.getElementById('adminLoadEarlierBtn');
        if (loadEarlierBtn) {
            loadEarlierBtn.addEventListener('click', loadOsasEarlierMessages);
        }
    }

    // Render messages
    function renderAdminMessages(messages, channel) {
        const isOsasThread = channel === 'osas';
        if (!messages || messages.length === 0) {
            const emptyText = isOsasThread
                ? 'No messages yet. Waiting for OSAS to message...'
                : 'No messages yet. Waiting for student to message...';
            return `<div style="text-align: center; color: #9ca3af; padding: 20px;">${emptyText}</div>`;
        }

        return messages.map((msg, i) => {
            const isSent = isOsasThread ? msg.sender_type === 'org' : msg.sender_type === 'admin';
            const messageClass = isSent ? 'sent' : 'received';
            const isTemp = msg.is_temp || (msg.id && msg.id.toString().startsWith('temp-'));
            const imageOnlyClass = isImageOnlyMessage(msg) ? ' admin-message-image-only' : '';
            const additionalClass = isTemp ? ' not-message-sent' : '';
            const attachmentHtml = buildAdminAttachmentHtml(resolveAttachmentMeta(msg));

            // Group times: consecutive messages from the same sender in the
            // same minute show a single time, on the latest message.
            let timeHtml = '';
            if (isTemp) {
                timeHtml = `<div class="admin-message-time">Sending...</div>`;
            } else {
                const next = messages[i + 1];
                const groupEnd = !next
                    || next.sender_type !== msg.sender_type
                    || (next.id && next.id.toString().startsWith('temp-'))
                    || formatTime(next.created_at) !== formatTime(msg.created_at);
                if (groupEnd) {
                    timeHtml = `<div class="admin-message-time">${formatTime(msg.created_at)}</div>`;
                }
            }

            return `
                <div class="admin-message ${messageClass}${imageOnlyClass}${additionalClass}" data-message-id="${msg.id}">
                    <div class="admin-message-content">
                        <p>${escapeHtml(msg.message || '')}</p>
                        ${attachmentHtml}
                    </div>
                    ${timeHtml}
                </div>
            `;
        }).join('');
    }

    // Find the optimistic bubble that matches a realtime row. Matching is done
    // by content (text + attachment presence) instead of position, so split
    // text+attachment sends never flip the wrong bubble.
    function findPendingBubble(container, row) {
        if (!container || !row) return null;
        const hasAttach = !!row.attachment_path;
        const bubbles = container.querySelectorAll('.admin-message');
        for (const bubble of bubbles) {
            const time = bubble.querySelector('.admin-message-time');
            if (!time || time.textContent !== 'Sending...') continue;
            const textEl = bubble.querySelector('.admin-message-content > p');
            const bubbleText = textEl ? textEl.textContent : '';
            const content = bubble.querySelector('.admin-message-content');
            const bubbleHasAttach = !!content.querySelector('.admin-attachment-image, .admin-message-content > span');
            if (bubbleText === (row.message || '') && bubbleHasAttach === hasAttach) return bubble;
        }
        return null;
    }

    // Append an incoming message bubble directly from the realtime event
    // payload - instant display without a server fetch.
    function appendMessageBubble(message, channel) {
        const messagesContainer = document.getElementById('adminConversationMessages');
        if (!messagesContainer || !message) return;
        // Attachment-only messages have an empty text body - allow them so
        // image/file bubbles still appear in realtime.
        if (!message.message && !message.attachment_path) return;

        // Skip if this message is already on screen (dedupe by id)
        if (messagesContainer.querySelector(`.admin-message[data-message-id="${message.id}"]`)) return;

        const isOsasThread = channel === 'osas';
        const isSent = isOsasThread ? message.sender_type === 'org' : message.sender_type === 'admin';
        const messageClass = isSent ? 'sent' : 'received';
        const imageOnlyClass = isImageOnlyMessage(message) ? ' admin-message-image-only' : '';
        const attachmentHtml = buildAdminAttachmentHtml(resolveAttachmentMeta(message));

        const html = `
            <div class="admin-message ${messageClass}${imageOnlyClass}" data-message-id="${message.id}">
                <div class="admin-message-content">
                    <p>${escapeHtml(message.message || '')}</p>
                    ${attachmentHtml}
                </div>
                <div class="admin-message-time">${formatTime(message.created_at)}</div>
            </div>
        `;

        messagesContainer.insertAdjacentHTML('beforeend', html);

        // Group times: if the appended message shares sender and minute with
        // the previous bubble, hide the previous bubble's time.
        const bubbles = messagesContainer.querySelectorAll('.admin-message');
        if (bubbles.length >= 2) {
            const prevBubble = bubbles[bubbles.length - 2];
            const prevTime = prevBubble.querySelector('.admin-message-time');
            if (prevTime
                && !prevTime.textContent.startsWith('Sending')
                && prevTime.textContent === formatTime(message.created_at)
                && prevBubble.classList.contains('sent') === isSent) {
                prevTime.style.display = 'none';
            }
        }

        // Only auto-scroll when the admin is already near the bottom, so
        // reading older history is not interrupted by incoming messages.
        if (messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 200) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    // Send admin message
    async function sendAdminMessage() {
        const messageInput = document.getElementById('adminMessageInput');
        if (!messageInput) return;

        const attachmentInput = document.getElementById('adminAttachmentInput');
        const attachmentPreview = document.getElementById('adminAttachmentPreview');
        const attachmentThumb = document.getElementById('adminAttachmentThumb');
        // Capture the selected file BEFORE anything clears the input, otherwise
        // FormData.append would serialize the cleared input as the string
        // "undefined" and the server would reject it as a non-file.
        const attachmentFile = attachmentInput && attachmentInput.files.length > 0
            ? attachmentInput.files[0]
            : null;
        const hasFile = !!attachmentFile;
        const messageText = messageInput.value.trim();
        if ((!messageText && !hasFile) || !currentStudentId) return;

        // OSAS thread sends go to the org->OSAS endpoint with sender_type 'org';
        // student sends keep the existing chatbot endpoint with 'admin'.
        const student = students.find(s => s.id == currentStudentId);
        const isOsasThread = !!(student && student.channel === 'osas');
        const mySenderType = isOsasThread ? 'org' : 'admin';

        // Fly the composer preview into the chat area before clearing.
        if (hasFile) {
            const messagesContainer = document.getElementById('adminConversationMessages');
            if (messagesContainer) {
                const previewSource = (attachmentThumb && !attachmentThumb.classList.contains('hidden'))
                    ? attachmentThumb
                    : (() => {
                        const tile = document.getElementById('adminAttachmentTile');
                        return tile && !tile.classList.contains('hidden') ? tile : null;
                    })();
                flyAttachmentToChat(previewSource, messagesContainer);
            }
        }

        // Clear input
        messageInput.value = '';
        if (hasFile && attachmentInput) {
            attachmentInput.value = '';
        }
        if (attachmentPreview) {
            attachmentPreview.classList.add('hidden');
            if (attachmentThumb) {
                if (attachmentThumb.src) URL.revokeObjectURL(attachmentThumb.src);
                attachmentThumb.removeAttribute('src');
                attachmentThumb.classList.add('hidden');
            }
            const previewTile = document.getElementById('adminAttachmentTile');
            if (previewTile) previewTile.classList.add('hidden');
        }

        try {
            // When text and an attachment are combined, they are sent as two
            // separate messages so the attachment gets its own bubble (text
            // first, then the attachment).
            if (messageText && hasFile) {
                await sendAdminSingle(messageText, null, isOsasThread, mySenderType);
                await sendAdminSingle('', attachmentFile, isOsasThread, mySenderType);
            } else {
                await sendAdminSingle(messageText, attachmentFile, isOsasThread, mySenderType);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }

    // Send a single admin message (text OR attachment, never both) with an
    // optimistic bubble and server confirmation.
    async function sendAdminSingle(messageText, attachmentFile, isOsasThread, mySenderType) {
        const hasFile = !!attachmentFile;

        // Add optimistic message to UI
        const tempMessage = {
            id: 'temp-' + Date.now(),
            message: messageText,
            sender_type: mySenderType,
            created_at: new Date().toISOString()
        };

        // Track the send so no background refresh can drop it before the
        // server confirms it.
        pendingSends[tempMessage.id] = {
            key: tempMessage.id,
            user_id: currentStudentId,
            message: messageText,
            sender_type: mySenderType,
            created_at: tempMessage.created_at,
            realId: null
        };

        // Update the contact list preview immediately ("sabay" with the bubble).
        touchStudentPreview(currentStudentId, tempMessage);

        // Preview object URL used by the optimistic bubble while the upload is
        // in flight - the image appears in the conversation immediately.
        const tempImageUrl = hasFile
            && attachmentFile.type && attachmentFile.type.indexOf('image/') === 0
            ? URL.createObjectURL(attachmentFile)
            : null;

        const messagesContainer = document.getElementById('adminConversationMessages');
        if (messagesContainer) {
            const imageOnlyClass = isImageOnlyMessage({
                attachment_path: hasFile ? 'pending' : null,
                message: messageText,
                is_image: tempImageUrl ? true : false
            }) ? ' admin-message-image-only' : '';
            const tempHtml = `
                <div class="admin-message sent admin-message-animate-in${imageOnlyClass}" data-message-id="${tempMessage.id}">
                    <div class="admin-message-content">
                        <p>${escapeHtml(messageText)}</p>
                        ${tempImageUrl ? `
                        <a href="javascript:void(0)" data-lightbox="${tempImageUrl}" class="admin-attachment-image">
                            <img src="${tempImageUrl}" data-temp-src="${tempImageUrl}" alt="Attachment preview" class="admin-chat-image admin-chat-image-pending">
                        </a>` : ''}
                        ${!tempImageUrl && hasFile ? `
                        <span style="display:inline-flex;margin-top:8px;background:#9ca3af;color:#fff;font-weight:800;font-size:10px;padding:7px 12px;border-radius:8px;letter-spacing:.5px;">${escapeHtml(fileTypeLabel(attachmentFile.name))}</span>` : ''}
                    </div>
                    <div class="admin-message-time">Sending...</div>
                </div>
            `;
            messagesContainer.insertAdjacentHTML('beforeend', tempHtml);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // The conversation may have been re-rendered while the request was in
        // flight, so always look up the container again and find the optimistic
        // message by its temp id instead of relying on a stale reference.
        const findTempMessage = function () {
            const currentContainer = document.getElementById('adminConversationMessages');
            if (!currentContainer) return null;
            return currentContainer.querySelector(`.admin-message[data-message-id="${tempMessage.id}"]`);
        };

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            let response;
            if (isOsasThread) {
                // Org -> OSAS send: the server forces the session organization.
                // Attachments must go through FormData; plain text can stay JSON.
                if (hasFile) {
                    const formData = new FormData();
                    formData.append('message', messageText);
                    formData.append('attachment', attachmentFile);
                    response = await fetch('/admin/chat/send', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                } else {
                    response = await fetch('/admin/chat/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ message: messageText })
                    });
                }
            } else {
                // Admin -> student send. Attachments must go through FormData;
                // plain text can stay JSON.
                if (hasFile) {
                    const formData = new FormData();
                    formData.append('_token', csrfToken);
                    formData.append('user_id', currentStudentId);
                    formData.append('message', messageText);
                    formData.append('attachment', attachmentFile);
                    response = await fetch('/admin/chatbot/send', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                } else {
                    response = await fetch('/admin/chatbot/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            user_id: currentStudentId,
                            message: messageText
                        })
                    });
                }
            }

            const data = await response.json();

            if (response.ok && data.success) {
                // Update the temporary message with the real message data
                const tempElement = findTempMessage();
                if (tempElement && tempElement.querySelector('.admin-message-time').textContent === 'Sending...') {
                    // Update the time to show it was sent
                    const timeElement = tempElement.querySelector('.admin-message-time');
                    if (timeElement) {
                        timeElement.textContent = formatTime(new Date().toISOString());
                        timeElement.style.color = '#d1d5db'; // Reset to normal color
                        timeElement.style.fontStyle = 'normal'; // Reset font style
                    }
                    // Update message content opacity
                    const contentElement = tempElement.querySelector('.admin-message-content');
                    if (contentElement) {
                        // Render the confirmed message (with attachment meta if any).
                        const confirmedMsg = resolveAttachmentMeta(data.message);
                        contentElement.innerHTML = `
                            <p>${escapeHtml(confirmedMsg.message || '')}</p>
                            ${buildAdminAttachmentHtml(confirmedMsg)}
                        `;
                        contentElement.style.opacity = '1';
                    }
                    // Release the optimistic object URL now that the server URL
                    // is rendered in its place.
                    if (tempImageUrl) URL.revokeObjectURL(tempImageUrl);
                    // Add sent indicator class
                    tempElement.classList.add('message-sent');
                    // Store the real message ID for future reference
                    tempElement.setAttribute('data-message-id', data.message.id);
                }
                // Confirm the pending send (re-keyed by its real id).
                const confirmedSend = pendingSends[tempMessage.id];
                if (confirmedSend) {
                    confirmedSend.realId = data.message.id;
                    confirmedSend.created_at = data.message.created_at;
                    delete pendingSends[tempMessage.id];
                    pendingSends[String(data.message.id)] = confirmedSend;
                }
                // Refresh the contact list preview with the confirmed timestamp.
                if (isOsasThread) {
                    touchOsasPreview({
                        message: messageText,
                        created_at: data.message.created_at,
                        sender_type: 'org'
                    });
                } else {
                    touchStudentPreview(currentStudentId, {
                        message: messageText,
                        created_at: data.message.created_at,
                        sender_type: 'admin'
                    });
                }
                // Reload students list to update last message (but don't reload messages)
                await loadStudents();
            } else {
                // Keep the message even if sending failed, just update the status
                const tempElement = findTempMessage();
                if (tempElement && tempElement.querySelector('.admin-message-time').textContent === 'Sending...') {
                    const timeElement = tempElement.querySelector('.admin-message-time');
                    if (timeElement) {
                        timeElement.textContent = 'Failed to send';
                        timeElement.style.color = '#ef4444'; // Red color for failed status
                        timeElement.style.fontStyle = 'italic';
                    }
                    // Add failed indicator
                    tempElement.classList.add('message-failed');
                }
                // Stop tracking the failed send - the DOM copy keeps showing it.
                delete pendingSends[tempMessage.id];
                // Don't restore message text since we want to keep the sent message visible
                console.error('Failed to send message, but keeping it in chat');
                console.error('Server response:', {
                    status: response.status,
                    body: data
                });
                // Surface the actual server error so the failure is diagnosable.
                const serverError = data && (data.error || data.message
                    || (data.errors ? Object.values(data.errors).flat().join('; ') : ''));
                if (serverError && window.showToast) {
                    window.showToast('Failed: ' + serverError, 'error');
                }
            }
        } catch (error) {
            console.error('Error sending message:', error);
            // Stop tracking the failed send - the DOM copy keeps showing it.
            delete pendingSends[tempMessage.id];
            // Keep the message even on network error, just update the status
            const tempElement = findTempMessage();
            if (tempElement && tempElement.querySelector('.admin-message-time').textContent === 'Sending...') {
                const timeElement = tempElement.querySelector('.admin-message-time');
                if (timeElement) {
                    timeElement.textContent = 'Connection error';
                    timeElement.style.color = '#ef4444'; // Red color for error status
                    timeElement.style.fontStyle = 'italic';
                }
                // Add failed indicator
                tempElement.classList.add('message-failed');
            }
            // Don't restore message text since we want to keep the sent message visible
        }
    }

    // Fetch and prepend the next older batch of OSAS messages.
    async function loadOsasEarlierMessages() {
        if (loadingEarlier || !currentStudentId || String(currentStudentId) !== 'osas') return;
        if (!osasOldestId || !osasHasMore) return;

        const widget = document.getElementById('admin-chat-widget');
        const orgId = widget ? widget.dataset.orgId : null;
        if (!orgId) return;

        loadingEarlier = true;
        const btn = document.getElementById('adminLoadEarlierBtn');
        const originalText = btn ? btn.textContent : '';
        if (btn) { btn.textContent = 'Loading...'; btn.disabled = true; }

        try {
            const response = await fetch(
                `/admin/chat/history?organization_id=${orgId}&before_id=${osasOldestId}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }
            );
            const data = await response.json();

            if (data.success && data.messages.length > 0) {
                const container = document.getElementById('adminConversationMessages');
                if (container) {
                    // Preserve scroll position relative to the oldest visible message.
                    const scrollAnchor = container.firstElementChild;
                    const anchorTop = scrollAnchor ? scrollAnchor.getBoundingClientRect().top - container.getBoundingClientRect().top : 0;
                    const anchorHeight = scrollAnchor ? scrollAnchor.offsetHeight : 0;

                    const frag = document.createDocumentFragment();
                    data.messages.forEach(msg => {
                        const resolved = resolveAttachmentMeta(msg);
                        const isSent = resolved.sender_type === 'org';
                        const node = document.createElement('div');
                        node.className = 'admin-message ' + (isSent ? 'sent' : 'received');
                        node.setAttribute('data-message-id', resolved.id);
                        node.innerHTML = `
                            <div class="admin-message-content">
                                <p>${escapeHtml(resolved.message || '')}</p>
                                ${buildAdminAttachmentHtml(resolved)}
                            </div>
                            <div class="admin-message-time">${formatTime(resolved.created_at)}</div>
                        `;
                        frag.appendChild(node);
                    });

                    if (scrollAnchor) {
                        container.insertBefore(frag, scrollAnchor);
                    } else {
                        container.appendChild(frag);
                    }

                    // Keep the view pinned to the same message the admin was
                    // reading so older history appears above it.
                    const anchor = container.querySelector('[data-message-id="' + scrollAnchor.dataset.messageId + '"]');
                    if (anchor) {
                        container.scrollTop += anchor.getBoundingClientRect().top - container.getBoundingClientRect().top - anchorTop;
                    }

                    osasOldestId = data.messages[0].id;
                }
            }

            osasHasMore = !!(data.has_more || false);
            const wrap = document.getElementById('adminLoadEarlierWrap');
            if (wrap) wrap.style.display = osasHasMore ? 'flex' : 'none';
        } catch (error) {
            console.error('Error loading earlier messages:', error);
        } finally {
            loadingEarlier = false;
            if (btn) { btn.textContent = originalText; btn.disabled = false; }
        }
    }

    // Utility functions
    // Ensure a chat message carries attachment_url/is_image, computing them from
    // the fixed download route and the stored path when the payload only has
    // raw columns (realtime).
    function resolveAttachmentMeta(msg) {
        if (!msg) return msg;
        if (msg.attachment_url || !msg.attachment_path) return msg;
        msg.attachment_url = '/admin/chat/' + msg.id + '/attachment';
        if (msg.is_image === undefined) {
            msg.is_image = isImagePath(msg.attachment_path);
        }
        if (msg.file_type === undefined) {
            msg.file_type = pathExtension(msg.attachment_path);
        }
        return msg;
    }

    const ATTACHMENT_IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    // 20MB server-side cap mirrored in the picker so oversized files are
    // rejected before they leave the browser.
    const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    function pathExtension(path) {
        const parts = String(path || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function isImagePath(path) {
        return ATTACHMENT_IMAGE_EXTS.indexOf(pathExtension(path)) !== -1;
    }

    // True when a message carries only an image (no text) - rendered as a
    // bare image with no bubble box.
    function isImageOnlyMessage(msg) {
        return !!(msg && msg.attachment_path && !(msg.message || '').trim()
            && (msg.is_image || isImagePath(msg.attachment_path)));
    }

    // File-type tile label + color shared by the composer preview and the
    // sent-message bubbles. No filename is ever shown.
    function fileTypeLabel(path) {
        const ext = pathExtension(path).toUpperCase();
        const labels = {
            PDF: 'PDF', DOC: 'DOC', DOCX: 'DOCX', XLS: 'XLS', XLSX: 'XLSX', CSV: 'CSV',
            PPT: 'PPT', PPTX: 'PPTX', TXT: 'TXT', ZIP: 'ZIP', RAR: 'RAR'
        };
        return labels[ext] || (ext || 'FILE');
    }

    function fileTypeColor(path) {
        const ext = pathExtension(path);
        const colors = {
            pdf: '#e74c3c', doc: '#2b579a', docx: '#2b579a', xls: '#217346', xlsx: '#217346', csv: '#217346',
            ppt: '#d24726', pptx: '#d24726', txt: '#6b7280', zip: '#f59e0b', rar: '#f59e0b'
        };
        return colors[ext] || '#9ca3af';
    }

    // Attachment bubble HTML: inline image thumbnail for images, a colored
    // file-type tile for every other file. Empty when the message has none.
    function buildAdminAttachmentHtml(msg) {
        if (!msg || !msg.attachment_path) return '';
        const url = msg.attachment_url || ('/admin/chat/' + msg.id + '/attachment');

        if (msg.is_image || isImagePath(msg.attachment_path)) {
            return `<a href="javascript:void(0)" data-lightbox="${url}" class="admin-attachment-image" style="display:block;margin-top:8px;max-width:180px;border-radius:10px;overflow:hidden;">
                <img src="${url}" alt="Attachment" style="width:100%;height:auto;display:block;background:#fff;">
            </a>`;
        }

        const label = fileTypeLabel(msg.attachment_path || msg.file_type);
        const color = fileTypeColor(msg.attachment_path || msg.file_type);
        const name = escapeHtml(msg.attachment_name || 'Attachment');
        return `<a href="${url}" download class="admin-attachment-file" style="display:inline-flex;align-items:center;gap:8px;margin-top:8px;padding:8px 12px;border-radius:10px;background:rgba(0,0,0,.06);text-decoration:none;color:inherit;max-width:220px;">
            <span style="background:${color};color:#fff;font-weight:800;font-size:10px;padding:5px 8px;border-radius:6px;letter-spacing:.5px;flex-shrink:0;">${label}</span>
            <span style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${name}</span>
        </a>`;
    }

    // Fly the composer preview element into the conversation area.
    function flyAttachmentToChat(previewEl, targetContainer) {
        if (!previewEl || !targetContainer) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const from = previewEl.getBoundingClientRect();
        const to = targetContainer.getBoundingClientRect();
        const clone = previewEl.cloneNode(true);
        clone.style.cssText = `position:fixed;left:${from.left}px;top:${from.top}px;width:${from.width}px;height:${from.height}px;z-index:9999;transition:all .4s cubic-bezier(.2,.8,.2,1);border-radius:8px;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.18);`;
        document.body.appendChild(clone);
        requestAnimationFrame(() => {
            clone.style.left = (to.left + to.width - 56) + 'px';
            clone.style.top = (to.top + 12) + 'px';
            clone.style.width = '52px';
            clone.style.height = '52px';
            clone.style.opacity = '.5';
        });
        setTimeout(() => clone.remove(), 420);
    }

    // Image lightbox: blurred backdrop, centered image, close on backdrop/ESC.
    function openImageModal(url) {
        const existing = document.getElementById('admin-lightbox');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'admin-lightbox';
        overlay.className = 'admin-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <button type="button" class="admin-lightbox-close" aria-label="Close image" title="Close">&times;</button>
            <img src="${url}" alt="Attachment" class="admin-lightbox-image">
        `;
        document.body.appendChild(overlay);

        const close = () => {
            overlay.classList.remove('admin-lightbox-open');
            document.removeEventListener('keydown', onKey);
            setTimeout(() => overlay.remove(), 200);
        };
        const onKey = (e) => { if (e.key === 'Escape') close(); };

        requestAnimationFrame(() => overlay.classList.add('admin-lightbox-open'));
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        overlay.querySelector('.admin-lightbox-close').addEventListener('click', close);
        document.addEventListener('keydown', onKey);
    }

    // Delegated lightbox clicks - covers optimistic and confirmed image bubbles.
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-lightbox]');
        if (trigger) {
            e.preventDefault();
            openImageModal(trigger.dataset.lightbox);
        }
    });

    function generateStudentAvatar(name, photo = null, role = null, email = null) {
        const osasLogoUrl = 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png';
        const normalizedName = String(name || '').trim().toLowerCase();
        const normalizedEmail = String(email || '').trim().toLowerCase();

        if (role === 'osas_admin'
            || normalizedEmail === 'osas@lspu.edu.ph'
            || normalizedName === 'osas administrator') {
            return `<img src="${osasLogoUrl}" alt="OSAS Administrator" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%; background: #ffffff; padding: 3px;">`;
        }

        if (photo) {
            // If student has a photo, show it
            return `<img src="/storage/${photo}" alt="${escapeHtml(name)}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        } else {
            // If no photo, show circle with first letter using CSS variable
            const initial = name.charAt(0).toUpperCase();
            return `<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--primary-color, #8238B3); color: white; font-weight: 600; font-size: 14px; border-radius: 50%;">${initial}</div>`;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatTime(timestamp) {
        if (!timestamp) return '';
        
        const date = new Date(timestamp);
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminChat);
    } else {
        initAdminChat();
    }

    // Make functions globally accessible if needed
    window.AdminFloatingChat = {
        init: initAdminChat,
        openChat: openAdminChatWindow,
        closeChat: closeAdminChatWindow
    };
})();
