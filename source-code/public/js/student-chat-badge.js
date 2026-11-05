// Student Chat Badge Update Script
(function() {
    'use strict';

    console.log('[BADGE] Student chat badge script loaded');

    // Only run for students (check if badge element exists)
    const badge = document.getElementById('student-chat-badge');
    const badgeMobile = document.getElementById('student-chat-badge-mobile');
    if (!badge) {
        console.log('[BADGE] Badge element not found - user might not be a student');
        return;
    }

    console.log('[BADGE] Badge element found:', badge);
    console.log('[BADGE] Initial badge classes:', badge.className);
    console.log('[BADGE] Initial badge text:', badge.textContent);

    // Function to update badge display
    function updateBadgeDisplay(count) {
        console.log('[BADGE] Updating badge display with count:', count);
        
        if (count > 0) {
            console.log('[BADGE] Showing badge with count:', count);
            badge.textContent = count;
            badge.style.display = 'inline-block'; // Use inline-block instead of empty string
            if (badgeMobile) {
                badgeMobile.textContent = count;
                badgeMobile.style.display = 'inline-block';
            }
        } else {
            console.log('[BADGE] Hiding badge (count is 0)');
            badge.style.display = 'none';
            if (badgeMobile) {
                badgeMobile.style.display = 'none';
            }
        }
    }

    // Function to fetch badge count (only used for initial load)
    async function updateBadgeCount() {
        try {
            console.log('[BADGE] Fetching unread count...');
            const response = await fetch('/chatbot/student-unread-count');
            
            if (!response.ok) {
                console.error('[BADGE] Response not OK:', response.status, response.statusText);
                return;
            }
            
            const data = await response.json();
            console.log('[BADGE] Received data:', data);
            
            const count = parseInt(data.count) || 0;
            updateBadgeDisplay(count);
        } catch (error) {
            console.error('[BADGE] Error fetching unread count:', error);
        }
    }

    // Setup realtime listener for chat updates (Supabase Realtime streams the
    // inserted admin_chats row straight from the database).
    function setupWebsocketListener() {
        if (typeof window.ChatRealtime !== 'undefined' && typeof window.ChatRealtime.onMessage === 'function') {
            console.log('[BADGE] Setting up Supabase Realtime listener for student chat...');

            window.ChatRealtime.onMessage((message) => {
                console.log('[BADGE] Received chat message via realtime:', message);

                // Update badge count when admin sends message to this student
                if (message && message.sender_type === 'admin') {
                    console.log('[BADGE] Admin message received, refreshing badge count');
                    updateBadgeCount();
                } else {
                    console.log('[BADGE] Student message received, ignoring for badge update');
                }
            });
        } else {
            // window.ChatRealtime may not exist yet if the app bundle is still
            // loading; retry until it becomes available.
            if (!window.__studentBadgeRealtimeRetry) {
                window.__studentBadgeRealtimeRetry = true;
                const retryTimer = setInterval(() => {
                    if (typeof window.ChatRealtime !== 'undefined' && typeof window.ChatRealtime.onMessage === 'function') {
                        clearInterval(retryTimer);
                        window.__studentBadgeRealtimeRetry = false;
                        setupWebsocketListener();
                    }
                }, 250);
                setTimeout(() => { clearInterval(retryTimer); window.__studentBadgeRealtimeRetry = false; }, 30000);
            }
            console.log('[BADGE] Falling back to polling every 10 seconds');
        }
        // Safety net: refresh the badge periodically regardless of the
        // websocket state (the student page also polls its messages).
        setInterval(updateBadgeCount, 10000);
    }

    // Make function globally accessible for manual updates
    window.updateBadgeCount = updateBadgeCount;

    // Setup websocket listener
    setupWebsocketListener();

    // Update immediately on page load (one-time only)
    console.log('[BADGE] Running initial update...');
    updateBadgeCount();

    // NO MORE POLLING - websocket handles real-time updates!
    console.log('[BADGE] Websocket-based updates enabled - no polling needed');
})();
