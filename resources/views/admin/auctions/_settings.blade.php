{{--
    The auction's rule settings: used to create an auction (defaults) and to
    edit one. Expects $values (team_purse, min_bid, bid_step, min_squad,
    max_squad, show_live_bids). All amounts are points.
--}}
<div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
    <x-form.input
        name="team_purse"
        :label="__('Purse for each team (points)')"
        type="number"
        inputmode="numeric"
        min="1"
        step="1"
        :value="$values['team_purse']"
        required
    />
    <x-form.input
        name="min_bid"
        :label="__('Minimum bid (points)')"
        type="number"
        inputmode="numeric"
        min="1"
        step="1"
        :value="$values['min_bid']"
        required
    />
    <x-form.input
        name="bid_step"
        :label="__('Bid goes up by (points)')"
        type="number"
        inputmode="numeric"
        min="1"
        step="1"
        :value="$values['bid_step']"
        required
    />
    <div class="grid gap-x-4 grid-cols-2">
        <x-form.input name="min_squad" :label="__('Minimum squad')" type="number" inputmode="numeric" min="1" max="50" :value="$values['min_squad']" required />
        <x-form.input name="max_squad" :label="__('Maximum squad')" type="number" inputmode="numeric" min="1" max="50" :value="$values['max_squad']" required />
    </div>
</div>

{{-- The rules above, in plain words, as they are typed. --}}
<p id="auction-rules-summary" class="mb-4 rounded-xl border border-brand/20 bg-brand-soft px-4 py-3 text-[13px] leading-relaxed text-slate-700" aria-live="polite"></p>

<input type="hidden" name="show_live_bids" value="0" />
<x-form.checkbox
    name="show_live_bids"
    :label="__('Show every bid live on the website')"
    :checked="$values['show_live_bids']"
    :help="__('Off shows only the result when a player is sold.')"
/>

<input type="hidden" name="notify_start" value="0" />
<x-form.checkbox
    name="notify_start"
    :label="__('Send a push notification when the auction starts and when it ends')"
    :checked="$values['notify_start']"
    :help="__('Goes to everyone who turned notifications on, the same way match results do.')"
/>

<div class="sm:max-w-xs">
    <x-form.input
        name="notify_sale_min"
        :label="__('Also notify when a player is sold for at least (points)')"
        type="number"
        inputmode="numeric"
        min="1"
        step="1"
        :value="$values['notify_sale_min']"
        :help="__('Leave empty to send nothing per sale — so nobody gets a message for every player.')"
    />
</div>

<script>
    (function () {
        var box = document.getElementById('auction-rules-summary');
        var emptyText = @json(__('Fill in the rules above and they are explained here.'));
        var summaryText = @json(__('In plain words: <b>every team gets :purse points</b>. A player starts at <b>:min</b> and each bid goes up by <b>:step</b>. A squad must have <b>:lo to :hi players</b>, and a team always keeps enough points to still reach the minimum squad.'));
        if (!box || box.dataset.ready) { return; }
        box.dataset.ready = '1';

        function field(id) { return document.getElementById(id); }
        function pts(value) {
            var number = Math.round(Number(value) || 0);
            var digits = String(Math.abs(number));
            if (digits.length > 3) {
                digits = digits.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + digits.slice(-3);
            }
            return (number < 0 ? '-' : '') + digits;
        }
        function draw() {
            var purse = field('team_purse').value, min = field('min_bid').value, step = field('bid_step').value;
            var lo = field('min_squad').value, hi = field('max_squad').value;
            if (!purse || !min || !step || !lo || !hi) { box.textContent = emptyText; return; }
            box.innerHTML = summaryText.split(':purse').join(pts(purse)).split(':min').join(pts(min)).split(':step').join(pts(step)).split(':lo').join(lo).split(':hi').join(hi);
        }
        ['team_purse', 'min_bid', 'bid_step', 'min_squad', 'max_squad'].forEach(function (id) {
            var input = field(id);
            if (input) { input.addEventListener('input', draw); }
        });
        draw();
    })();
</script>
