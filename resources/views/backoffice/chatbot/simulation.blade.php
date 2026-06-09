<x-backoffice.layout pageTitle="Simulasi Chatbot KedaiBot">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Top Title and Instructions Card -->
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Simulator KedaiBot</h2>
                    <p class="text-sm font-semibold text-slate-500 mt-1">Uji interaksi chatbot dari sudut pandang customer mobile app.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-[#C5620B]/20 bg-[#FCB861]/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#6A2B09]">
                        Mode Admin Simulation
                    </span>
                </div>
            </div>
            
            <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-100 text-xs text-slate-600 space-y-1">
                <p class="font-bold text-slate-700">Petunjuk Penggunaan:</p>
                <ul class="list-disc pl-4 space-y-0.5">
                    <li>Gunakan tombol quick action pada balon chat pertama untuk memicu skenario utama chatbot.</li>
                    <li>Simulator ini menyimpan riwayat chat di local browser Anda. Klik tombol reset (ikon sampah/refresh) di header mobile jika ingin mengulang simulasi dari awal.</li>
                    <li>Anda juga dapat mengetik perintah <code class="bg-slate-200 px-1 py-0.5 rounded font-mono">//clear</code> di kolom chat untuk membersihkan riwayat.</li>
                </ul>
            </div>
        </article>

        <!-- Smartphone Emulator Outer Wrapper -->
        <div class="flex justify-center py-2">
            <!-- Smartphone Shell -->
            <div class="w-[380px] min-w-[380px] h-[780px] bg-zinc-950 rounded-[3rem] p-3 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.4)] border-4 border-zinc-800 flex flex-col relative overflow-hidden select-none">
                <!-- Camera Notch / Pill -->
                <div class="absolute top-4 left-1/2 -translate-x-1/2 w-32 h-6 bg-zinc-950 rounded-full z-50 flex items-center justify-between px-4">
                    <span class="w-2.5 h-2.5 bg-zinc-900 border border-zinc-800 rounded-full"></span>
                    <span class="w-12 h-1 bg-zinc-900 rounded-full"></span>
                </div>

                <!-- Screen Area -->
                <div class="w-full h-full bg-white rounded-[2.3rem] overflow-hidden flex flex-col relative border border-zinc-900">
                    <!-- Status Bar Spacer (For Notch) -->
                    <div class="h-9 bg-white w-full flex justify-between items-center px-6 pt-1 text-[11px] font-bold text-zinc-800">
                        <span>22:46</span>
                        <div class="flex items-center gap-1.5">
                            <!-- Signal Icon -->
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61L4.35 19.4c-.39.39-.39 1.02 0 1.41.39.39 1.02.39 1.41 0l1.9-1.9C9.07 19.57 10.48 20 12 20c4.97 0 9-4.03 9-9s-4.03-9-9-9zm0 15c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z"/></svg>
                            <!-- Wifi Icon -->
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61L4.35 19.4c-.39.39-.39 1.02 0 1.41.39.39 1.02.39 1.41 0l1.9-1.9C9.07 19.57 10.48 20 12 20c4.97 0 9-4.03 9-9s-4.03-9-9-9zm0 15c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z"/></svg>
                            <!-- Battery Icon -->
                            <div class="w-5 h-2.5 border border-zinc-800 rounded-sm p-0.5 flex items-center"><div class="h-full w-full bg-zinc-800 rounded-[1px]"></div></div>
                        </div>
                    </div>

                    <!-- Chat Header -->
                    <header class="h-14 bg-white border-b border-slate-100 px-3 flex items-center justify-between z-20 shadow-sm shrink-0">
                        <div class="flex items-center gap-2">
                            <!-- Back Button -->
                            <a href="/backoffice/chatbot-analytics" class="p-1 hover:bg-slate-100 rounded-full transition text-slate-800" title="Kembali ke Analytics">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </a>
                            <!-- Bot Profile Picture & Identity -->
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <div class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden p-0.5">
                                        <img src="/images/kedaibot.png" alt="KedaiBot" class="w-full h-full object-contain" onerror="this.src='/images/default.jpg'">
                                    </div>
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-extrabold text-slate-800 leading-none">KedaiBot</span>
                                    <span class="text-[10px] font-semibold text-emerald-500">Online</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reset Chat Button -->
                        <button id="reset-chat-btn" class="p-1.5 hover:bg-slate-100 rounded-full text-slate-400 hover:text-rose-600 transition" title="Bersihkan Percakapan">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </header>

                    <!-- Chat Body & Messages View -->
                    <main id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-white flex flex-col no-scrollbar">
                        <!-- Messages will be injected here dynamically by JavaScript -->
                    </main>

                    <!-- Quick Action Chips Panel (Conditionally Visible After Greeting) -->
                    <div id="quick-action-panel" class="bg-white border-t border-slate-50 py-2 px-3 flex gap-2 overflow-x-auto no-scrollbar shrink-0 select-none hidden">
                        <button class="quick-action-chip px-4 py-2 bg-[#xFFFFEEE1] text-[#C6620C] text-xs font-bold rounded-full border border-orange-100 hover:bg-[#ffebd6] transition whitespace-nowrap shadow-sm animate-fade-in" data-message="Pesan Makanan" data-action="greeting_order">
                            Pesan Makanan
                        </button>
                        <button class="quick-action-chip px-4 py-2 bg-[#xFFFFEEE1] text-[#C6620C] text-xs font-bold rounded-full border border-orange-100 hover:bg-[#ffebd6] transition whitespace-nowrap shadow-sm animate-fade-in" data-message="Tracking Pesanan" data-action="greeting_tracking">
                            Tracking Pesanan
                        </button>
                        <button class="quick-action-chip px-4 py-2 bg-[#xFFFFEEE1] text-[#C6620C] text-xs font-bold rounded-full border border-orange-100 hover:bg-[#ffebd6] transition whitespace-nowrap shadow-sm animate-fade-in" data-message="Rekomendasi Menu" data-action="greeting_recommendation">
                            Rekomendasi Menu
                        </button>
                        <button class="quick-action-chip px-4 py-2 bg-[#xFFFFEEE1] text-[#C6620C] text-xs font-bold rounded-full border border-orange-100 hover:bg-[#ffebd6] transition whitespace-nowrap shadow-sm animate-fade-in" data-message="Lihat Keranjang" data-action="greeting_view_cart">
                            Lihat Keranjang
                        </button>
                    </div>

                    <!-- Input Bar -->
                    <footer class="p-3 bg-white border-t border-slate-100 flex items-center gap-2 shrink-0 z-10 shadow-[0_-4px_12px_rgba(0,0,0,0.02)]">
                        <input id="chat-input" type="text" placeholder="Ketik Pesan..." class="flex-1 border border-slate-300 rounded-full px-4 py-2.5 text-sm outline-none focus:border-orange-500 transition placeholder-slate-400 font-medium bg-slate-50/50 focus:bg-white" autocomplete="off">
                        <button id="send-btn" class="w-10 h-10 rounded-full bg-[#C6620C] text-white flex items-center justify-center shadow-md shadow-orange-700/20 hover:bg-[#b0550a] active:scale-95 transition shrink-0">
                            <!-- Paper Plane Icon -->
                            <svg class="w-5 h-5 translate-x-[1px]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </footer>
                </div>
            </div>
        </div>
    </div>

    <!-- Styling rules to hide scrollbars for cleaner visual mobile emulation -->
    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <!-- Chatbot Simulation Logic Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chatMessages = document.getElementById('chat-messages');
            const chatInput = document.getElementById('chat-input');
            const sendBtn = document.getElementById('send-btn');
            const resetBtn = document.getElementById('reset-chat-btn');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const localStorageKey = 'kedaibot_simulation_history';

            let messages = [];
            let isProcessing = false;

            // Load chat history from LocalStorage
            function loadChatHistory() {
                const stored = localStorage.getItem(localStorageKey);
                if (stored) {
                    try {
                        messages = JSON.parse(stored);
                    } catch (e) {
                        messages = [];
                    }
                }

                if (messages.length === 0) {
                    // Inject initial greeting message
                    messages = [{
                        isUser: false,
                        text: "Halo, Administrator! Saya bisa bantu simulasi pesan makanan, tracking pesanan, rekomendasi menu, atau lihat keranjang.",
                        actions: [
                            { label: "Pesan Makanan", value: "greeting_order" },
                            { label: "Tracking Pesanan", value: "greeting_tracking" },
                            { label: "Rekomendasi Menu", value: "greeting_recommendation" },
                            { label: "Lihat Keranjang", value: "greeting_view_cart" }
                        ],
                        cards: []
                    }];
                    saveChatHistory();
                }

                renderMessages();
            }

            // Save chat history to LocalStorage
            function saveChatHistory() {
                localStorage.setItem(localStorageKey, JSON.stringify(messages));
            }

            // Scroll chat to the bottom
            function scrollToBottom() {
                setTimeout(() => {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }, 50);
            }

            // Render all message bubbles
            function renderMessages() {
                chatMessages.innerHTML = '';

                messages.forEach((msg, idx) => {
                    const row = document.createElement('div');
                    row.className = msg.isUser ? 'flex justify-end w-full' : 'flex items-start gap-2 w-full';

                    if (msg.isUser) {
                        row.innerHTML = `
                            <div class="max-w-[75%] bg-[#C6620C] text-white px-4 py-3 rounded-2xl rounded-tr-none shadow-sm text-sm leading-relaxed font-semibold">
                                ${escapeHtml(msg.text)}
                            </div>
                        `;
                    } else {
                        // Bot message contains Avatar and bubble
                        let actionsHtml = '';
                        if (msg.actions && msg.actions.length > 0) {
                            actionsHtml = `
                                <div class="flex flex-wrap gap-2 mt-2">
                                    ${msg.actions.map(act => `
                                        <button class="bot-action-btn px-3.5 py-2 bg-[#xFFFFEEE1] text-[#C6620C] border border-orange-100 hover:bg-[#ffebd6] text-xs font-bold rounded-full transition shadow-sm" data-label="${escapeHtml(act.label)}" data-value="${escapeHtml(act.value)}">
                                            ${escapeHtml(act.label)}
                                        </button>
                                    `).join('')}
                                </div>
                            `;
                        }

                        let cardsHtml = '';
                        if (msg.cards && msg.cards.length > 0) {
                            cardsHtml = `
                                <div class="space-y-3 mt-2.5 w-full">
                                    ${msg.cards.map(card => renderCard(card)).join('')}
                                </div>
                            `;
                        }

                        row.innerHTML = `
                            <!-- Bot Avatar column -->
                            <div class="flex flex-col items-center shrink-0 w-12 mt-1">
                                <div class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden p-0.5">
                                    <img src="/images/kedaibot.png" alt="KedaiBot" class="w-full h-full object-contain" onerror="this.src='/images/default.jpg'">
                                </div>
                                <span class="text-[9px] font-bold text-slate-400 mt-1 leading-none text-center">KedaiBot</span>
                            </div>
                            <!-- Bubble column -->
                            <div class="max-w-[75%] flex flex-col">
                                <div class="bg-[#F3F3F3] text-slate-800 px-4 py-3 rounded-2xl rounded-tl-none text-sm leading-relaxed font-medium">
                                    ${formatReplyText(msg.text)}
                                </div>
                                ${cardsHtml}
                                ${actionsHtml}
                            </div>
                        `;
                    }

                    chatMessages.appendChild(row);
                });

                // Attach click handlers to any rendered bot action buttons
                document.querySelectorAll('.bot-action-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const label = this.getAttribute('data-label');
                        const value = this.getAttribute('data-value');
                        triggerAction(label, value);
                    });
                });

                // Attach click handlers to card ordering buttons
                document.querySelectorAll('.card-order-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const menuName = this.getAttribute('data-menu-name');
                        const menuId = this.getAttribute('data-menu-id');
                        triggerAction(`Pesan ${menuName}`, `suggest_menu:${menuId}`);
                    });
                });

                // Toggle quick action panel visibility based on conversation progress (beyond initial greeting)
                const actionPanel = document.getElementById('quick-action-panel');
                if (actionPanel) {
                    if (messages.length > 1) {
                        actionPanel.classList.remove('hidden');
                    } else {
                        actionPanel.classList.add('hidden');
                    }
                }

                scrollToBottom();
            }

            // Render structural chatbot cards (Menu, Order Summary, Tracking)
            function renderCard(card) {
                if (card.type === 'menu_card' && card.menu) {
                    const menu = card.menu;
                    const imageUrl = normalizeMenuImageUrl(menu.image_url);
                    return `
                        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col p-3 gap-3">
                            <div class="flex gap-3">
                                <img src="${imageUrl}" class="w-16 h-16 rounded-xl object-cover border border-slate-100 shrink-0" onerror="this.src='/images/default.jpg'">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-slate-800 truncate leading-tight">${escapeHtml(menu.menu_name)}</h4>
                                    <p class="text-[10px] text-slate-500 line-clamp-2 mt-0.5">${escapeHtml(menu.description || '')}</p>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs font-extrabold text-[#C6620C]">Rp${formatNumber(menu.price)}</span>
                                        <span class="text-[9px] font-bold text-slate-400">Stok ${menu.stock}</span>
                                    </div>
                                </div>
                            </div>
                            <button class="card-order-btn w-full py-1.5 bg-[#C6620C] text-white text-[11px] font-bold rounded-lg hover:bg-[#b0550a] transition uppercase tracking-wider" data-menu-id="${escapeHtml(menu.menu_id)}" data-menu-name="${escapeHtml(menu.menu_name)}">
                                Pesan Menu
                            </button>
                        </div>
                    `;
                }

                if (card.type === 'order_summary_card' && card.items) {
                    return `
                        <div class="bg-white border border-slate-200 rounded-2xl p-3 shadow-sm space-y-2.5">
                            <div class="border-b border-slate-100 pb-1.5 flex items-center justify-between">
                                <span class="text-xs font-extrabold text-slate-800">Ringkasan Pesanan</span>
                                <span class="text-[10px] font-bold text-orange-500 uppercase tracking-wide">Checkout</span>
                            </div>
                            <div class="space-y-1.5">
                                ${card.items.slice(0, 3).map(item => `
                                    <div class="flex justify-between text-xs text-slate-700">
                                        <span class="truncate pr-4">${escapeHtml(item.name)} x${item.quantity}</span>
                                        <span class="font-semibold shrink-0">Rp${formatNumber(item.subtotal)}</span>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="border-t border-slate-100 pt-2 flex justify-between items-center text-xs font-extrabold text-slate-800">
                                <span>Total</span>
                                <span class="text-[#C6620C]">Rp${formatNumber(card.total)}</span>
                            </div>
                        </div>
                    `;
                }

                if (card.type === 'tracking_status_card') {
                    const shortId = card.order_id.length > 6 
                        ? card.order_id.substring(card.order_id.length - 6).toUpperCase() 
                        : card.order_id.toUpperCase();
                    return `
                        <div class="bg-white border border-slate-200 rounded-2xl p-3 shadow-sm space-y-2.5">
                            <div class="border-b border-slate-100 pb-1.5 flex items-center justify-between">
                                <span class="text-xs font-extrabold text-slate-800">Order #${shortId}</span>
                                <span class="text-[9px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full uppercase tracking-wider">${escapeHtml(card.status_label)}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-y-1.5 gap-x-2 text-[11px] text-slate-600">
                                <div><span class="text-slate-400">Metode Bayar:</span> <p class="font-bold text-slate-700">${escapeHtml(card.payment_status)}</p></div>
                                <div><span class="text-slate-400">Nomor Antrean:</span> <p class="font-bold text-slate-700">${card.queue_number}</p></div>
                                <div><span class="text-slate-400">Total Harga:</span> <p class="font-bold text-slate-700">Rp${formatNumber(card.total_price)}</p></div>
                                <div><span class="text-slate-400">Tanggal:</span> <p class="font-bold text-slate-700">${escapeHtml(card.tracking_date_label || '-')}</p></div>
                            </div>
                        </div>
                    `;
                }

                return '';
            }

            // Normalizes menu imageUrls
            function normalizeMenuImageUrl(raw) {
                if (!raw) return '/images/default.jpg';
                const value = raw.trim();
                if (value === '') return '/images/default.jpg';
                if (value.startsWith('http://') || value.startsWith('https://')) return value;
                if (value.startsWith('/storage/menu/')) {
                    const parts = value.split('/');
                    return `/v1/menus/image/${parts[parts.length - 1]}`;
                }
                return value.startsWith('/') ? value : `/${value}`;
            }

            // Trigger action from quick action pills or card buttons
            function triggerAction(label, value) {
                if (isProcessing) return;
                sendMessage(label, value);
            }

            // Append bot typing indicator
            function showTypingIndicator() {
                const indicator = document.createElement('div');
                indicator.id = 'typing-indicator';
                indicator.className = 'flex items-start gap-2 w-full';
                indicator.innerHTML = `
                    <div class="flex flex-col items-center shrink-0 w-12 mt-1">
                        <div class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden p-0.5">
                            <img src="/images/kedaibot.png" alt="KedaiBot" class="w-full h-full object-contain">
                        </div>
                        <span class="text-[9px] font-bold text-slate-400 mt-1 leading-none text-center">KedaiBot</span>
                    </div>
                    <div class="bg-[#F3F3F3] text-slate-500 px-4 py-3.5 rounded-2xl rounded-tl-none text-sm flex items-center gap-1 mt-6">
                        <span class="w-2.5 h-2.5 bg-slate-400 rounded-full animate-bounce [animation-duration:1s]"></span>
                        <span class="w-2.5 h-2.5 bg-slate-400 rounded-full animate-bounce [animation-duration:1s] [animation-delay:0.2s]"></span>
                        <span class="w-2.5 h-2.5 bg-slate-400 rounded-full animate-bounce [animation-duration:1s] [animation-delay:0.4s]"></span>
                    </div>
                `;
                chatMessages.appendChild(indicator);
                scrollToBottom();
            }

            // Remove bot typing indicator
            function removeTypingIndicator() {
                const indicator = document.getElementById('typing-indicator');
                if (indicator) {
                    indicator.remove();
                }
            }

            // Send message to backoffice chatbot simulation API
            async function sendMessage(text, action = '') {
                if (isProcessing) return;
                const trimmed = text.trim();
                if (trimmed === '' && action === '') return;

                // Check for client-side clear command
                if (trimmed === '//clear') {
                    clearChat();
                    chatInput.value = '';
                    return;
                }

                // Add user bubble to messages
                if (trimmed !== '') {
                    messages.push({
                        isUser: true,
                        text: trimmed,
                        actions: [],
                        cards: []
                    });
                    saveChatHistory();
                    renderMessages();
                }

                chatInput.value = '';
                isProcessing = true;
                setFormEnabled(false);
                showTypingIndicator();

                try {
                    const response = await fetch('/backoffice/chatbot-simulation/message', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            message: trimmed,
                            action: action
                        })
                    });

                    const result = await response.json();
                    removeTypingIndicator();

                    if (result.status === 'success' && result.data) {
                        const botData = result.data;
                        messages.push({
                            isUser: false,
                            text: botData.reply || '',
                            actions: botData.actions || [],
                            cards: botData.cards || []
                        });
                        saveChatHistory();
                        renderMessages();
                    } else {
                        // Error fallback
                        messages.push({
                            isUser: false,
                            text: result.message || 'Maaf, terjadi kesalahan sistem saat memproses pesan.',
                            actions: [],
                            cards: []
                        });
                        saveChatHistory();
                        renderMessages();
                    }
                } catch (e) {
                    removeTypingIndicator();
                    messages.push({
                        isUser: false,
                        text: 'Maaf, gagal menghubungi server simulator. Periksa koneksi Anda.',
                        actions: [],
                        cards: []
                    });
                    saveChatHistory();
                    renderMessages();
                } finally {
                    isProcessing = false;
                    setFormEnabled(true);
                    scrollToBottom();
                }
            }

            function setFormEnabled(enabled) {
                chatInput.disabled = !enabled;
                sendBtn.disabled = !enabled;
                if (enabled) {
                    sendBtn.classList.remove('opacity-50', 'pointer-events-none');
                    chatInput.classList.remove('opacity-50');
                } else {
                    sendBtn.classList.add('opacity-50', 'pointer-events-none');
                    chatInput.classList.add('opacity-50');
                }
            }

            function clearChat() {
                messages = [{
                    isUser: false,
                    text: "Halo, Administrator! Saya bisa bantu simulasi pesan makanan, tracking pesanan, rekomendasi menu, atau lihat keranjang.",
                    actions: [
                        { label: "Pesan Makanan", value: "greeting_order" },
                        { label: "Tracking Pesanan", value: "greeting_tracking" },
                        { label: "Rekomendasi Menu", value: "greeting_recommendation" },
                        { label: "Lihat Keranjang", value: "greeting_view_cart" }
                    ],
                    cards: []
                }];
                saveChatHistory();
                renderMessages();
            }

            // Text formatting (line breaks to <br>)
            function formatReplyText(text) {
                return escapeHtml(text).replace(/\n/g, '<br>');
            }

            // Escape HTML helper
            function escapeHtml(string) {
                return String(string)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Currency formatter helper
            function formatNumber(num) {
                return new Intl.NumberFormat('id-ID').format(num);
            }

            // Event listener for sending on button click
            sendBtn.addEventListener('click', () => {
                sendMessage(chatInput.value);
            });

            // Event listener for sending on Enter press
            chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    sendMessage(chatInput.value);
                }
            });

            // Reset chat history on trash button click
            resetBtn.addEventListener('click', () => {
                if (confirm('Bersihkan riwayat percakapan simulasi ini?')) {
                    clearChat();
                }
            });

            // Quick action chips click listeners
            document.querySelectorAll('.quick-action-chip').forEach(chip => {
                chip.addEventListener('click', function() {
                    const text = this.getAttribute('data-message');
                    const action = this.getAttribute('data-action');
                    triggerAction(text, action);
                });
            });

            // Run initial load
            loadChatHistory();
        });
    </script>
</x-backoffice.layout>
