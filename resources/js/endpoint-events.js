/**
 * Alpine component backing the endpoint live view.
 *
 * Realtime: private per-endpoint Reverb channels. `.captured` prepends a
 * compact row; `.delivery.updated` patches that row's delivery badge
 * (including the exhausted/DLQ state). Full payload loads on demand.
 */
export function endpointEvents({ token, endpointId, initial, urls }) {
    return {
        token,
        endpointId,
        urls,
        events: initial,
        selected: null,
        detail: null,
        detailLoading: false,
        replayBusy: {},
        flash: null,

        init() {
            if (!window.Echo) {
                return;
            }

            window.Echo.private(`endpoint.${this.token}`)
                .listen('.captured', (e) => {
                    this.events.unshift({
                        id: e.id,
                        method: e.method,
                        path: e.path,
                        content_type: e.content_type,
                        payload_size: e.payload_size,
                        received_at: e.received_at,
                        deliveries: [],
                        preview: null,
                    });
                })
                .listen('.delivery.updated', (e) => {
                    const event = this.events.find((ev) => ev.id === e.event_id);
                    if (!event) {
                        return;
                    }

                    event.deliveries = [
                        { attempt: e.attempt, status_code: e.status_code, delivered: e.delivered, error: e.error },
                        ...event.deliveries.filter((d) => d.attempt !== e.attempt),
                    ];

                    if (e.exhausted) {
                        this.flashOnce(`Delivery to target exhausted retries (event #${e.event_id}) — dead-lettered.`);
                    }
                });
        },

        async open(id) {
            this.selected = id;
            this.detail = null;
            this.detailLoading = true;

            try {
                const res = await fetch(`${urls.event}/${id}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.detail = await res.json();
            } finally {
                this.detailLoading = false;
            }
        },

        async replay(id) {
            this.replayBusy[id] = true;

            try {
                const res = await fetch(`${urls.event}/${id}/replay`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                });

                const body = await res.json();

                this.flashOnce(res.ok ? 'Replay dispatched to queue.' : (body.error ?? 'Replay failed.'));
            } finally {
                this.replayBusy[id] = false;
            }
        },

        prettyBody(body, contentType) {
            if (!body) {
                return '';
            }

            if ((contentType ?? '').includes('json')) {
                try {
                    return JSON.stringify(JSON.parse(body), null, 2);
                } catch {
                    return body;
                }
            }

            return body;
        },

        flashOnce(message) {
            this.flash = message;
            setTimeout(() => (this.flash = null), 6000);
        },
    };
}
