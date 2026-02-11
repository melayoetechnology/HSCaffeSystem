<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use Livewire\Component;

new class extends Component
{
    public int $lastSeenOrderId = 0;

    public int $lastSeenConfirmedOrderId = 0;

    private function sessionKey(string $suffix): string
    {
        $tenantId = auth()->user()->tenant_id ?? 0;

        return "new_order_notifier.{$tenantId}.{$suffix}";
    }

    public function mount(): void
    {
        $maxPending = (int) Order::where('status', OrderStatus::Pending->value)->max('id');
        $maxConfirmed = (int) Order::where('status', OrderStatus::Confirmed->value)->max('id');
        $this->lastSeenOrderId = max(
            $maxPending,
            (int) session($this->sessionKey('last_pending_id'), 0)
        );
        $this->lastSeenConfirmedOrderId = max(
            $maxConfirmed,
            (int) session($this->sessionKey('last_confirmed_id'), 0)
        );
        session([
            $this->sessionKey('last_pending_id') => $this->lastSeenOrderId,
            $this->sessionKey('last_confirmed_id') => $this->lastSeenConfirmedOrderId,
        ]);
    }

    public function checkNewOrders(): void
    {
        $this->checkNewPendingOrders();
        if (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::Kitchen)) {
            $this->checkNewlyConfirmedOrders();
        }
        $this->refreshSidebarCounts();
    }

    protected function refreshSidebarCounts(): void
    {
        $pending = Order::where('status', OrderStatus::Pending->value)->count();
        $kitchen = Order::whereIn('status', [
            OrderStatus::Confirmed->value,
            OrderStatus::Preparing->value,
        ])->count();
        $this->js(sprintf(
            'typeof window.__updateSidebarCounts === "function" && window.__updateSidebarCounts(%d, %d);',
            $pending,
            $kitchen
        ));
    }

    protected function checkNewPendingOrders(): void
    {
        $newOrders = Order::query()
            ->with('table')
            ->where('status', OrderStatus::Pending->value)
            ->where('id', '>', $this->lastSeenOrderId)
            ->orderBy('id')
            ->get();

        if ($newOrders->isEmpty()) {
            return;
        }

        $primaryColor = auth()->user()->tenant?->primary_color ?? '#6366f1';

        foreach ($newOrders as $order) {
            $heading = __('Pesanan Baru');
            $tableLabel = $order->table
                ? __('Meja').' '.$order->table->number
                : __('Take Away / Dine In');
            $text = $order->order_number.' — '.$tableLabel;

            $this->js(sprintf(
                "window.__showNewOrderToast(%s, %s, %s); window.__playNewOrderSound && window.__playNewOrderSound();",
                json_encode($heading),
                json_encode($text),
                json_encode($primaryColor)
            ));
        }

        $this->lastSeenOrderId = (int) $newOrders->max('id');
        session([$this->sessionKey('last_pending_id') => $this->lastSeenOrderId]);
    }

    protected function checkNewlyConfirmedOrders(): void
    {
        $newConfirmed = Order::query()
            ->with('table')
            ->where('status', OrderStatus::Confirmed->value)
            ->where('id', '>', $this->lastSeenConfirmedOrderId)
            ->orderBy('id')
            ->get();

        if ($newConfirmed->isEmpty()) {
            return;
        }

        foreach ($newConfirmed as $order) {
            $heading = __('Makanan Perlu Disiapkan');
            $tableLabel = $order->table
                ? __('Meja').' '.$order->table->number
                : __('Take Away / Dine In');
            $text = $order->order_number.' — '.$tableLabel;

            $this->js(sprintf(
                "window.__showKitchenToast(%s, %s); window.__playKitchenSound && window.__playKitchenSound();",
                json_encode($heading),
                json_encode($text)
            ));
        }

        $this->lastSeenConfirmedOrderId = (int) $newConfirmed->max('id');
        session([$this->sessionKey('last_confirmed_id') => $this->lastSeenConfirmedOrderId]);
    }
};
?>

<div wire:poll.3s="checkNewOrders" class="hidden" aria-hidden="true"></div>

<script>
    (function () {
        function playBeep(context) {
            try {
                const o = context.createOscillator();
                const g = context.createGain();
                o.connect(g);
                g.connect(context.destination);
                o.frequency.value = 880;
                o.type = 'sine';
                g.gain.value = 0.25;
                o.start(0);
                g.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.15);
                o.stop(context.currentTime + 0.15);
                setTimeout(() => {
                    const o2 = context.createOscillator();
                    const g2 = context.createGain();
                    o2.connect(g2);
                    g2.connect(context.destination);
                    o2.frequency.value = 1100;
                    o2.type = 'sine';
                    g2.gain.value = 0.2;
                    o2.start(context.currentTime + 0.2);
                    g2.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.35);
                    o2.stop(context.currentTime + 0.35);
                }, 200);
            } catch (e) {}
        }

        window.__playNewOrderSound = function () {
            let ctx = window.__newOrderAudioCtx;
            if (!ctx) {
                ctx = new (window.AudioContext || window.webkitAudioContext)();
                window.__newOrderAudioCtx = ctx;
            }
            if (ctx.state === 'suspended') {
                ctx.resume().then(() => playBeep(ctx));
            } else {
                playBeep(ctx);
            }
        };

        function playKitchenBeep(context, freq, startTime, duration) {
            const o = context.createOscillator();
            const g = context.createGain();
            o.connect(g);
            g.connect(context.destination);
            o.frequency.value = freq;
            o.type = 'sine';
            g.gain.setValueAtTime(0.25, startTime);
            g.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
            o.start(startTime);
            o.stop(startTime + duration);
        }

        window.__playKitchenSound = function () {
            let ctx = window.__newOrderAudioCtx;
            if (!ctx) {
                ctx = new (window.AudioContext || window.webkitAudioContext)();
                window.__newOrderAudioCtx = ctx;
            }
            if (ctx.state === 'suspended') {
                ctx.resume().then(() => playKitchenSoundBeeps(ctx));
            } else {
                playKitchenSoundBeeps(ctx);
            }
        };

        function playKitchenSoundBeeps(context) {
            const t = context.currentTime;
            playKitchenBeep(context, 520, t, 0.12);
            playKitchenBeep(context, 520, t + 0.18, 0.12);
            playKitchenBeep(context, 520, t + 0.36, 0.12);
        }

        window.__showNewOrderToast = function (heading, text, primaryColor) {
            primaryColor = primaryColor || '#6366f1';
            const container = document.getElementById('new-order-toast-container');
            if (!container) {
                console.warn('New order toast: container #new-order-toast-container not found');
                return;
            }
            const el = document.createElement('div');
            el.className = 'flex rounded-xl shadow-lg border border-white/20 overflow-hidden';
            el.style.backgroundColor = primaryColor;
            el.style.color = 'white';
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="flex flex-1 items-start gap-3 p-4"><div class="flex-1 min-w-0"><div class="font-semibold text-sm">' + escapeHtml(heading) + '</div><div class="mt-0.5 text-sm text-white/90">' + escapeHtml(text) + '</div></div></div>';
            container.appendChild(el);
            el.style.opacity = '0';
            el.style.transform = 'translateY(-0.5rem)';
            requestAnimationFrame(() => {
                el.style.transition = 'opacity 0.2s, transform 0.2s';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-0.5rem)';
                setTimeout(() => el.remove(), 200);
            }, 6000);
        };

        window.__showKitchenToast = function (heading, text) {
            const container = document.getElementById('new-order-toast-container');
            if (!container) return;
            const el = document.createElement('div');
            el.className = 'flex rounded-xl shadow-lg overflow-hidden border-2 border-amber-400 dark:border-amber-500 bg-gradient-to-r from-amber-600 to-orange-600 dark:from-amber-700 dark:to-orange-700';
            el.style.color = 'white';
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="flex flex-1 items-start gap-3 p-4 w-full"><div class="flex shrink-0 mt-0.5 rounded-full bg-white/25 p-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 15.75.75.75 0 016 15v-3a.75.75 0 01.75-.75 5.25 5.25 0 001.316-10.223 7.547 7.547 0 01-1.647-2.761.75.75 0 00-1.071-.136 6 6 0 008.5 8.5 7.547 7.547 0 01-2.761-1.647z" clip-rule="evenodd" /></svg></div><div class="flex-1 min-w-0"><div class="font-bold text-sm uppercase tracking-wide text-amber-100">' + escapeHtml(heading) + '</div><div class="mt-1 text-sm font-medium">' + escapeHtml(text) + '</div></div></div>';
            container.appendChild(el);
            el.style.opacity = '0';
            el.style.transform = 'translateY(-0.5rem)';
            requestAnimationFrame(() => {
                el.style.transition = 'opacity 0.25s, transform 0.25s';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-0.5rem)';
                setTimeout(() => el.remove(), 250);
            }, 7000);
        };

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        document.addEventListener('click', function unlockAudio() {
            if (window.__newOrderAudioCtx && window.__newOrderAudioCtx.state === 'suspended') {
                window.__newOrderAudioCtx.resume();
            }
        }, { once: true });
    })();
</script>
