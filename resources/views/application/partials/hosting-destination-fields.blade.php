@php
    $jannayaks = \App\Models\Application::HOSTING_DESTINATION_JANNAYAKS;
    $iconsherald = \App\Models\Application::HOSTING_DESTINATION_ICONSHERALD;
@endphp
<fieldset class="stack" style="margin:0;padding:0;border:0">
    <legend class="sub" style="padding:0">Hosting destination</legend>
    <label class="row" style="gap:8px;align-items:flex-start;margin:0">
        <input type="radio" name="hosting_destination" value="{{ $jannayaks }}" class="js-hosting-choice"
               @checked($selectedHosting === $jannayaks) required>
        <span>
            <b>Jannayaks</b>
            <span class="sub" style="display:block">jannayaks.in/your.name</span>
        </span>
    </label>
    <label class="row" style="gap:8px;align-items:flex-start;margin:0">
        <input type="radio" name="hosting_destination" value="{{ $iconsherald }}" class="js-hosting-choice"
               @checked($selectedHosting === $iconsherald) required>
        <span>
            <b>IconsHerald</b>
            <span class="sub" style="display:block">iconsherald.com/your.name</span>
        </span>
    </label>
    @if(! empty($slugPreview))
        <p class="sub" style="margin:0">
            Preview:
            <code id="hosting-preview">{{ $hostPrefix }}{{ $slugPreview }}</code>
        </p>
    @endif
</fieldset>
