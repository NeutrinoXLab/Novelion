<div
    x-data="novelionChat()"
    x-init="init()"
    class="fixed bottom-6 right-6 z-50"
>

    {{-- Butonul plutitor --}}
    <button
        type="button"
        @click="toggle()"
        class="w-16 h-16 rounded-full bg-cyan-500 hover:bg-cyan-600 text-white shadow-2xl flex items-center justify-center transition duration-300 hover:scale-105"
        aria-label="Deschide chat"
    >

        <template x-if="!open">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-7 h-7"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8H5l-3 2 1-4a8 8 0 1117-6z"
                />
            </svg>
        </template>

        <template x-if="open">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-7 h-7"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18L18 6M6 6l12 12"
                />
            </svg>
        </template>

    </button>


    {{-- Fereastra chat --}}
    <div
        x-show="open"
        x-transition
        class="absolute bottom-20 right-0 w-[360px] max-w-[calc(100vw-2rem)] bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden"
        style="display: none;"
    >

        {{-- Header --}}
        <div class="bg-cyan-500 text-white px-5 py-4">

            <div class="flex items-center justify-between">

                <div class="flex items-center gap-3">

                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-xl">
                        💬
                    </div>

                    <div>

                        <h3 class="font-bold">
                            Novelion
                        </h3>

                        <p class="text-xs text-cyan-50">
                            Suntem aici să te ajutăm
                        </p>

                    </div>

                </div>

                <button
                    type="button"
                    @click="close()"
                    class="text-white/80 hover:text-white text-xl"
                >
                    ×
                </button>

            </div>

        </div>


        {{-- Mesaje --}}
        <div
            x-ref="messages"
            class="h-80 overflow-y-auto bg-slate-50 px-4 py-4 space-y-3"
        >

            <template x-if="loading">

                <div class="text-center text-sm text-slate-400 py-6">
                    Se încarcă...
                </div>

            </template>


            <template x-if="!loading && messages.length === 0">

                <div class="text-center py-10">

                    <div class="text-4xl mb-3">
                        👋
                    </div>

                    <p class="font-semibold text-slate-800">
                        Bună!
                    </p>

                    <p class="text-sm text-slate-500 mt-1">
                        Cu ce te putem ajuta?
                    </p>

                </div>

            </template>


            <template x-for="message in messages" :key="message.id">

                <div
                    class="flex"
                    :class="message.sender_type === 'customer'
                        ? 'justify-end'
                        : 'justify-start'"
                >

                    <div
                        class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-5"
                        :class="message.sender_type === 'customer'
                            ? 'bg-cyan-500 text-white rounded-br-md'
                            : 'bg-white text-slate-700 border border-slate-200 rounded-bl-md'"
                    >

                        <p x-text="message.message"></p>

                    </div>

                </div>

            </template>


            <template x-if="error">

                <div class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-3 py-2 text-sm">
                    <span x-text="error"></span>
                </div>

            </template>

        </div>


        {{-- Formular mesaj --}}
        <form
            @submit.prevent="sendMessage()"
            class="border-t border-slate-200 bg-white p-3"
        >

            <div class="flex items-center gap-2">

                <input
                    type="text"
                    x-model="newMessage"
                    maxlength="2000"
                    autocomplete="off"
                    placeholder="Scrie un mesaj..."
                    class="flex-1 min-w-0 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100"
                >

                <button
                    type="submit"
                    :disabled="sending || !newMessage.trim()"
                    class="w-11 h-11 shrink-0 rounded-full bg-cyan-500 hover:bg-cyan-600 disabled:opacity-50 disabled:cursor-not-allowed text-white flex items-center justify-center transition"
                    aria-label="Trimite mesaj"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 12h14M12 5l7 7-7 7"
                        />
                    </svg>

                </button>

            </div>

        </form>

    </div>

</div>


<script>
    function novelionChat() {
        return {

            open: false,

            loading: false,

            sending: false,

            error: '',

            messages: [],

            newMessage: '',


            init() {
                // Chatul este încărcat doar când utilizatorul îl deschide.
            },


            toggle() {
                this.open = !this.open;

                if (this.open && this.messages.length === 0) {
                    this.loadMessages();
                }
            },


            close() {
                this.open = false;
            },


            async loadMessages() {

                this.loading = true;
                this.error = '';

                try {

                    const response = await fetch('{{ route('chat.index') }}', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Nu am putut încărca conversația.');
                    }

                    const data = await response.json();

                    this.messages = data.messages || [];

                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });

                } catch (error) {

                    this.error = error.message || 'A apărut o eroare.';

                } finally {

                    this.loading = false;

                }
            },


            async sendMessage() {

                const message = this.newMessage.trim();

                if (!message || this.sending) {
                    return;
                }

                this.sending = true;
                this.error = '';

                try {

                    const response = await fetch('{{ route('chat.send') }}', {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },

                        body: JSON.stringify({
                            message: message,
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message || 'Mesajul nu a putut fi trimis.'
                        );
                    }

                    if (data.message) {
                        this.messages.push(data.message);
                    }

                    this.newMessage = '';

                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });

                } catch (error) {

                    this.error = error.message || 'A apărut o eroare.';

                } finally {

                    this.sending = false;

                }
            },


            scrollToBottom() {

                if (this.$refs.messages) {
                    this.$refs.messages.scrollTop =
                        this.$refs.messages.scrollHeight;
                }

            },

        };
    }
</script>