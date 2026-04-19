/**
 * Advanced Community Analytics using IntersectionObserver and Page Visibility API
 * Tracks dwell time and impressions without blocking the main thread.
 */

class AnalyticsTracker {
    constructor() {
        // Accumulators for metrics to send in batches
        this.metrics = {
            posts: {},      // e.g. { postId: { dwellTime: x, impressions: y, readTime: z, views: w } }
            comments: {}    // e.g. { commentId: { readTime: x } }
        };

        this.visibleElements = new Map(); // Maps DOM element to its config (type, id, startTime)
        this.isPageVisible = !document.hidden;

        // Configuration
        this.endpoint = '/user-and-diag/community/analytics/beacon';
        this.syncInterval = 10000; // Force sync every 10s if active

        this.initObserver();
        this.initVisibilityAPI();

        // Start background sync loop
        setInterval(() => this.sendBeacon(), this.syncInterval);
    }

    initObserver() {
        this.observer = new IntersectionObserver((entries) => {
            const now = Date.now();
            entries.forEach(entry => {
                const el = entry.target;
                if (entry.isIntersecting) {
                    // Element came into view
                    if (!this.visibleElements.has(el)) {
                        this.visibleElements.set(el, {
                            type: el.dataset.trackType, // 'post-feed', 'post-detail', 'comment'
                            id: el.dataset.trackId,
                            startTime: now
                        });

                        // Register an impression/view immediately if applicable
                        if (el.dataset.trackType === 'post-feed') {
                            this._incrementStat('posts', el.dataset.trackId, 'impressions', 1);
                        } else if (el.dataset.trackType === 'post-detail') {
                            this._incrementStat('posts', el.dataset.trackId, 'views', 1);
                            // Avoid double tracking views on the same page load
                            el.removeAttribute('data-track-type');
                            el.dataset.trackType = 'post-detail-read';
                            this.visibleElements.get(el).type = 'post-detail-read';
                        } else if (el.dataset.trackType === 'completed-read') {
                            this._incrementStat('posts', el.dataset.trackId, 'completedReads', 1);
                            el.removeAttribute('data-track-type');
                            this.observer.unobserve(el);
                            this.visibleElements.delete(el);
                        }
                    }
                } else {
                    // Element went out of view
                    if (this.visibleElements.has(el)) {
                        this._accrueTime(el, now);
                        this.visibleElements.delete(el);
                    }
                }
            });
        }, { threshold: 0.5 }); // Element must be at least 50% visible to count
    }

    initVisibilityAPI() {
        document.addEventListener('visibilitychange', () => {
            const now = Date.now();
            if (document.hidden) {
                // User switched tabs - pause timers by accruing time and updating start points
                this.isPageVisible = false;
                this.visibleElements.forEach((config, el) => {
                    this._accrueTime(el, now);
                });
                // Send immediately when backgrounded
                this.sendBeacon();
            } else {
                // User came back
                this.isPageVisible = true;
                this.visibleElements.forEach((config, el) => {
                    config.startTime = now; // Reset start time
                });
            }
        });

        // Ensure beacon is sent on actual navigation/close
        window.addEventListener('pagehide', () => {
            if (this.isPageVisible) {
                const now = Date.now();
                this.visibleElements.forEach((config, el) => {
                    this._accrueTime(el, now);
                });
            }
            this.sendBeacon(true);
        });
    }

    _accrueTime(el, now) {
        const config = this.visibleElements.get(el);
        if (!config || !this.isPageVisible) return;

        // Calculate seconds spent looking at it
        const elapsedSecs = Math.round((now - config.startTime) / 1000);
        if (elapsedSecs <= 0) return;

        if (config.type === 'post-feed') {
            this._incrementStat('posts', config.id, 'dwellTime', elapsedSecs);
        } else if (config.type.startsWith('post-detail')) {
            this._incrementStat('posts', config.id, 'readTime', elapsedSecs);
        } else if (config.type === 'comment') {
            this._incrementStat('comments', config.id, 'readTime', elapsedSecs);
        }

        // Advance start timer so we don't double count if we sync while it's still visible
        config.startTime = now;
    }

    _incrementStat(category, id, statName, value) {
        if (!this.metrics[category][id]) {
            this.metrics[category][id] = {};
        }
        if (!this.metrics[category][id][statName]) {
            this.metrics[category][id][statName] = 0;
        }
        this.metrics[category][id][statName] += value;
    }

    observe(selector) {
        document.querySelectorAll(selector).forEach(el => {
            this.observer.observe(el);
        });
    }

    sendBeacon(isUnload = false) {
        // Also accrue time for currently visible elements before sending
        const now = Date.now();
        if (this.isPageVisible) {
            this.visibleElements.forEach((config, el) => {
                this._accrueTime(el, now);
            });
        }

        if (Object.keys(this.metrics.posts).length === 0 && Object.keys(this.metrics.comments).length === 0) {
            return;
        }

        const payload = JSON.stringify(this.metrics);

        // Reset metrics
        this.metrics = { posts: {}, comments: {} };

        if (isUnload && navigator.sendBeacon) {
            navigator.sendBeacon(this.endpoint, payload);
        } else {
            fetch(this.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(e => console.log('Analytics sync failed', e));
        }
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    window.communityTracker = new AnalyticsTracker();

    // Auto-observe elements with data-track-type
    window.communityTracker.observe('[data-track-type]');

    // Track media clicks
    document.addEventListener('click', (e) => {
        const img = e.target.closest('.post-image');
        if (img && img.dataset.postId) {
            window.communityTracker._incrementStat('posts', img.dataset.postId, 'mediaClicks', 1);
            window.communityTracker.sendBeacon(); // force sync for immediate click
        }
    });
});
