<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<div x-data="{
    open: false,
    input: '',
    messages: [],
    isLoading: false,
    getCompanyId() {
        // Try URL params first (Superadmin uses ?company_id=xxx)
        const urlParams = new URLSearchParams(window.location.search);
        const fromUrl = urlParams.get('company_id');
        if (fromUrl) return fromUrl;
        // Fallback to Blade-injected session value (regular admin)
        return '{{ session('active_company_id', '') }}';
    },
    sendMessage() {
        if (!this.input.trim() || this.isLoading) return;
        this.messages.push({ role: 'user', content: this.input });
        this.input = '';
        this.isLoading = true;
        this.scrollToBottom();

        fetch('/admin/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
            },
            body: JSON.stringify({
                messages: this.messages,
                company_id: this.getCompanyId()
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.role && data.content) {
                this.messages.push({ role: data.role, content: data.content });
            } else if (data.error) {
                this.messages.push({ role: 'assistant', content: 'Erreur: ' + data.error });
            }
        })
        .catch(err => {
            this.messages.push({ role: 'assistant', content: 'Erreur de connexion.' });
        })
        .finally(() => {
            this.isLoading = false;
            this.scrollToBottom();
        });
    },
    scrollToBottom() {
        setTimeout(() => {
            const container = this.$refs.chatContainer;
            if (container) container.scrollTop = container.scrollHeight;
        }, 50);
    }
}" class="fixed bottom-6 right-6 z-50">
    <!-- Chat Toggle Button -->
    <button @click="open = !open"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-900 text-white shadow-lg transition-transform hover:scale-105 focus:outline-none ring-4 ring-slate-900/20">
        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
        <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    <!-- Chat Panel -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-10 scale-95"
         x-cloak
         class="absolute bottom-20 right-0 w-[400px] h-[600px] max-h-[80vh] max-w-[90vw] overflow-hidden rounded-2xl border border-white/20 bg-white shadow-2xl flex flex-col">
         
         <!-- Header -->
         <div class="flex items-center justify-between border-b border-slate-100 bg-white px-4 py-3 shrink-0">
             <div class="flex items-center gap-3">
                 <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white shadow-sm">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                     </svg>
                 </div>
                 <div>
                     <h3 class="text-sm font-semibold text-slate-900">Assistant IA</h3>
                     <p class="text-xs text-slate-500">CarriereOS Native Bot</p>
                 </div>
             </div>
             <button @click="open = false" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                 </svg>
             </button>
         </div>

         <!-- Messages Area -->
         <div x-ref="chatContainer" class="flex-1 overflow-y-auto p-4 bg-slate-50 space-y-4">
             <template x-if="messages.length === 0">
                 <div class="flex flex-col items-center justify-center h-full text-slate-400 space-y-3">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                     </svg>
                     <p class="text-sm">Comment puis-je vous aider ?</p>
                 </div>
             </template>
             <template x-for="(msg, index) in messages" :key="index">
                 <!-- Filter out tool_result messages visually -->
                 <div x-show="msg.role !== 'user' || typeof msg.content === 'string'" :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                     <div class="inline-block p-3 rounded-xl max-w-[85%] text-sm shadow-sm overflow-hidden"
                          :class="msg.role === 'user' ? 'bg-slate-900 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none prose prose-sm prose-slate'">
                          <span x-html="typeof msg.content === 'string' ? (msg.role === 'user' ? msg.content.replace(/\n/g, '<br>') : (window.marked ? marked.parse(msg.content) : msg.content.replace(/\n/g, '<br>'))) : 'Requête interne...'"></span>
                     </div>
                 </div>
             </template>
             
             <!-- Typing Indicator -->
             <div x-show="isLoading" class="flex justify-start">
                 <div class="bg-white border border-slate-200 text-slate-800 p-3 rounded-xl rounded-bl-none shadow-sm flex items-center space-x-1">
                     <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                     <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                     <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                 </div>
             </div>
         </div>

         <!-- Input Area -->
         <div class="border-t border-slate-100 bg-white p-3 shrink-0">
             <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                 <input type="text" x-model="input" placeholder="Posez une question..." 
                        class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none transition-colors focus:border-slate-300 focus:bg-white"
                        :disabled="isLoading" />
                 <button type="submit" 
                         :disabled="isLoading || !input.trim()"
                         class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white transition-all hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-0.5" viewBox="0 0 20 20" fill="currentColor">
                         <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                     </svg>
                 </button>
             </form>
         </div>
    </div>
</div>
