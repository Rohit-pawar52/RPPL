{{--
    The auction's rule settings: used to create an auction (defaults) and to
    edit one. Expects $values (team_purse, min_bid, bid_step, min_squad,
    max_squad, show_live_bids). All amounts are points.
--}}
<div class="grid gap-x-4 sm:grid-cols-2">
    <x-form.input
        name="team_purse"
        label="Purse for each team (points)"
        type="number"
        min="1"
        step="1"
        :value="$values['team_purse']"
        required
    />
    <x-form.input
        name="min_bid"
        label="Minimum bid (points)"
        type="number"
        min="1"
        step="1"
        :value="$values['min_bid']"
        required
    />
    <x-form.input
        name="bid_step"
        label="Bid goes up by (points)"
        type="number"
        min="1"
        step="1"
        :value="$values['bid_step']"
        required
    />
    <div class="grid gap-x-4 grid-cols-2">
        <x-form.input name="min_squad" label="Minimum squad" type="number" min="1" max="50" :value="$values['min_squad']" required />
        <x-form.input name="max_squad" label="Maximum squad" type="number" min="1" max="50" :value="$values['max_squad']" required />
    </div>
</div>

<input type="hidden" name="show_live_bids" value="0" />
<x-form.checkbox
    name="show_live_bids"
    label="Show every bid live on the website"
    :checked="$values['show_live_bids']"
    help="Off shows only the result when a player is sold."
/>
