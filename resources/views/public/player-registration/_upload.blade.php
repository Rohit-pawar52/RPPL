{{--
    A picture picker of the public registration form: the shared preview box
    (<x-form.image-upload>) - the photo, or the default picture while there is
    none, that opens the file chooser when tapped, and shows what was chosen.
    A file over the server's limit is refused straight away.
    Expects: $name, $label, $accept, $hint, $emptyText, $kind ('user' for the
    player's photo, 'image' for the payment screenshot), $maxBytes,
    $tooLarge; optional: $required (default true).
--}}
<x-form.image-upload
    :name="$name"
    :label="$label"
    :accept="$accept"
    :required="$required ?? true"
    :kind="$kind"
    box-class="h-16 w-16 rounded-xl"
    :empty-text="$emptyText"
    :change-text="__('registration.upload.change_hint')"
    :help="$hint"
    :max-bytes="$maxBytes"
    :too-large="$tooLarge"
/>
