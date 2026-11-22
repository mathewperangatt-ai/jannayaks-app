{{-- Shared publication-ready profile presentation.
     Included by public/profile.blade.php (the published page) and by the
     customer preview so both render byte-for-byte the same presentation.
     Parent must provide the presentation data from
     PublicProfilePresentationService (profile, photos, photo, displayName,
     profession, headline, locationLabel, publicOffices, activeEditorial,
     malayalam, language, videoLinks, viewerReactions, referenceCode,
     canonicalUrl, isDemonstration, listingUrl, listingLabel).

     Preview-context overrides (all optional, default to public behaviour):
     - photoUrlOf:           ?Closure(MediaItem): string — src for photographs
     - langUrlOf:            ?Closure(string $lang): string — language toggle href
     - showEngagement:       bool (default true) — Like/Applaud/Contact rail
     - showPermanentUrl:     bool (default true) — URL pill + copy/share
     - galleryLinksClickable: bool (default true) — gallery anchors
     - showBackLink:         bool (default true) — "← Back to …" link
--}}
@php
    // Preview-context overrides; the published page passes none of them.
    $photoUrlOf = $photoUrlOf ?? null;
    $langUrlOf = $langUrlOf ?? null;
    $showEngagement = $showEngagement ?? true;
    $showPermanentUrl = $showPermanentUrl ?? true;
    $galleryLinksClickable = $galleryLinksClickable ?? true;
    $showBackLink = $showBackLink ?? true;

    $__photoUrl = fn ($item) => $photoUrlOf
        ? ($photoUrlOf)($item)
        : route('profiles.public.photo', [$profile, $item]);
    $__langUrl = fn (string $lang) => $langUrlOf
        ? ($langUrlOf)($lang)
        : route('profiles.public', ['slug' => $profile->slug, 'lang' => $lang]);

    $nameWords = preg_split('/\s+/u', trim($displayName), -1, PREG_SPLIT_NO_EMPTY);
    $nameFirst = implode(' ', array_slice($nameWords, 0, -1));
    $nameLast = $nameWords ? array_pop($nameWords) : '';

    // Editorial photograph placements (Phase 3). Approved placements only —
    // supplied by the presentation service. Stable SECTION anchors hold
    // across English and Malayalam; paragraph structure never matters.
    $placements = $placements ?? collect();
    // All approved profile photographs flow inline inside the biography
    // article (hero/gallery/sidebar presentation removed). Approved
    // placements set the order; unplaced photographs follow in profile order.
    $inlinePhotos = $placements->sortBy('display_order')->map(fn ($pl) => $pl->mediaItem)
        ->merge($photos->diff($placements->map(fn ($pl) => $pl->mediaItem)));
    $inlinePlacements = $placements->filter(fn ($pl) => $pl->placement_type === 'inline');
    // No photograph renders twice: hero + inline placements are excluded from the gallery.
    $placedPhotoIds = $placements->pluck('media_item_id')->map(fn ($id) => (int) $id)->all();
@endphp

@push('head')
<style>
    /* ————— Approved profile visual standard —————
       Sage ground, deep blue serif typography, restrained saffron accents,
       framed portrait, editorial columns. Scoped under .jk-page. */
    .jk-page{--jk-bg:#f3f8f0;--jk-surface:#f7faf5;--jk-surface2:#eaf2e7;--jk-blue:#214d68;--jk-blue2:#52758a;
        --jk-saffron:#c77e2e;--jk-green:#86a982;--jk-ink:#1f2924;--jk-muted:#6f8075;--jk-line:#d3ddd1;
        --jk-serif:'Fraunces','Cormorant Garamond',Georgia,serif;--jk-ui:'DM Sans',system-ui,sans-serif;
        background:var(--jk-bg);color:var(--jk-ink);margin:-28px -22px -56px;padding:0}
    .jk-wrap{max-width:1180px;margin:0 auto;padding:26px clamp(18px,4vw,42px) 72px}
    .jk-crumbs{font:500 11px var(--jk-ui);letter-spacing:.14em;text-transform:uppercase;color:var(--jk-muted);margin:0 0 26px}
    .jk-crumbs a{color:var(--jk-muted);text-decoration:none}
    .jk-crumbs a:hover{color:var(--jk-blue)}
    .jk-crumbs span[aria-current]{color:var(--jk-blue)}

    /* hero */
    .jk-hero{display:block;padding:8px 0 64px;border-bottom:1px solid var(--jk-line);max-width:820px}
    /* --whole: keep the full photograph (no cover crop). Used for demonstration
       portraits (watermark must stay visible) and when the biography side feature
       reuses the only available photograph. */
    .jk-eyebrow{font:600 11px var(--jk-ui);letter-spacing:.22em;text-transform:uppercase;color:var(--jk-saffron);margin:0}
    .jk-name{font:500 clamp(32px,3.9vw,54px)/1.04 var(--jk-serif);color:var(--jk-blue);margin:20px 0 8px;letter-spacing:-.01em}
    .jk-name span{color:var(--jk-saffron)}
    .jk-org{font:italic clamp(17px,1.7vw,21px) var(--jk-serif);color:var(--jk-blue2);margin:0 0 12px}
    .jk-location{font:500 10px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:var(--jk-muted);margin:0 0 26px}
    .jk-intro{font-size:clamp(17px,1.8vw,19px);line-height:1.6;border-left:5px solid #8eaf88;padding:4px 0 4px 22px;max-width:620px;margin:0}
    .jk-url-label{font:600 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#7d9b79;margin:30px 0 8px}
    .jk-urlrow{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .jk-urlpill,.jk-copy{height:42px;border:1px solid #b8c9b5;background:rgba(255,255,255,.36);padding:0 15px;display:inline-flex;align-items:center;font:500 12.5px var(--jk-ui);color:var(--jk-blue);border-radius:2px}
    .jk-copy{background:var(--jk-blue);color:#fff;border-color:var(--jk-blue);cursor:pointer;text-transform:uppercase;letter-spacing:.1em;font-size:10px;font-weight:600}
    .jk-copy:hover{background:var(--jk-saffron);border-color:var(--jk-saffron)}
    .jk-lang{display:inline-flex;border:1px solid #b8c9b5;border-radius:2px;margin-left:6px;background:rgba(255,255,255,.36)}
    .jk-lang a{height:40px;display:inline-flex;align-items:center;padding:0 16px;font:500 13.5px var(--jk-ui);color:var(--jk-blue);text-decoration:none}
    .jk-lang a + a{border-left:1px solid #b8c9b5}
    .jk-lang a[lang="ml"]{font-family:'Noto Serif Malayalam',var(--jk-ui)}
    .jk-lang a:hover{background:#fff}
    .jk-lang a[aria-current="true"]{color:var(--jk-saffron);font-weight:600;background:#fffdf8;box-shadow:inset 0 -2px 0 var(--jk-saffron)}
    .jk-demo-note{margin:16px 0 0;font:500 11px var(--jk-ui);color:var(--jk-muted);letter-spacing:.06em}

    /* well-wisher rail: contact + reactions */
    .jk-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .jk-engage{margin-top:48px}
    .jk-engage-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
    .jk-rail{grid-column:3}
    .jk-hero.with-rail{grid-template-columns:minmax(0,30fr) minmax(0,48fr) minmax(0,22fr)}
    .jk-box{background:var(--jk-surface);border:1px solid var(--jk-line);padding:22px;margin-bottom:16px}
    .jk-box h2{font:600 12px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:var(--jk-blue);margin:0 0 6px}
    .jk-box .jk-box-sub{font:400 12.5px/1.55 var(--jk-ui);color:var(--jk-muted);margin:0 0 14px}
    .jk-field{margin-bottom:10px}
    .jk-field label{display:block;font:600 10px var(--jk-ui);letter-spacing:.12em;text-transform:uppercase;color:var(--jk-muted);margin-bottom:4px}
    .jk-field input,.jk-field textarea{width:100%;padding:9px 11px;border:1px solid #c3d2c0;background:#fff;font:400 13.5px var(--jk-ui);color:var(--jk-ink);border-radius:2px}
    .jk-field textarea{min-height:74px;resize:vertical}
    .jk-send{width:100%;padding:10px 14px;border:0;background:var(--jk-blue);color:#fff;font:600 11px var(--jk-ui);letter-spacing:.12em;text-transform:uppercase;cursor:pointer;border-radius:2px}
    .jk-send:hover{background:var(--jk-saffron)}
    .jk-privacy{font:400 11px/1.5 var(--jk-ui);color:var(--jk-muted);margin:10px 0 0}
    .jk-status{margin:0 0 12px;padding:9px 12px;background:#e7f0e4;border-left:3px solid var(--jk-green);font:500 12.5px var(--jk-ui);color:#33553f}
    .jk-status.err{background:#f6ebe6;border-left-color:#b3652f;color:#6b3a1d}
    .jk-reactions{display:flex;gap:8px}
    .jk-react{flex:1;padding:10px 8px;border:1px solid #b8c9b5;background:var(--jk-surface);font:600 12px var(--jk-ui);color:var(--jk-blue);cursor:pointer;border-radius:2px}
    .jk-react:hover{border-color:var(--jk-saffron);color:var(--jk-saffron)}
    .jk-react.on{background:var(--jk-blue);border-color:var(--jk-blue);color:#fff}
    .jk-react-login{display:block;text-align:center;padding:10px;border:1px dashed #b8c9b5;color:var(--jk-muted);font:500 12px var(--jk-ui);text-decoration:none;border-radius:2px}
    .jk-react-login:hover{color:var(--jk-blue)}

    /* sections */
    .jk-section{padding-top:64px}
    .jk-sectionhead{display:grid;grid-template-columns:auto auto 1fr auto;gap:16px;align-items:center;border-bottom:1px solid var(--jk-line);padding-bottom:12px;margin-bottom:36px}
    .jk-sectionhead h2{font:500 clamp(26px,3.4vw,42px)/1.05 var(--jk-serif);color:var(--jk-blue);margin:0}
    .jk-tail{font:600 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#6e9180;justify-self:end;text-align:right}
    .jk-bodytext{max-width:600px;font-size:16.5px;line-height:1.78}
    .jk-bodytext p{margin:0 0 24px}
    .jk-bodytext p:first-child::first-letter{font:500 3.4em/0.82 var(--jk-serif);float:left;padding:8px 10px 0 0;color:var(--jk-saffron)}
    .jk-figcap{font:500 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#81907e;margin-top:8px}
    .jk-inline-photo{margin:10px auto 28px;max-width:640px}
    .jk-inline-photo a{display:block}
    .jk-inline-photo img{width:100%;height:auto;display:block;border:1px solid #bdcfba;background:var(--jk-surface2)}
    .jk-inline-photo figcaption{font:500 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#81907e;margin-top:8px}

    /* ledger + lists */
    .jk-ledgerrow{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:26px;padding:20px 0;border-bottom:1px solid var(--jk-line)}
    .jk-ledgerrow:first-of-type{border-top:1px solid var(--jk-line)}
    .jk-ledgerrow strong{font:500 17px var(--jk-serif);color:var(--jk-blue);display:block}
    .jk-ledgerrow .where{font:500 10px var(--jk-ui);letter-spacing:.14em;text-transform:uppercase;color:var(--jk-muted);margin:3px 0 0}
    .jk-ledgerrow span.d{font-size:15px;color:#46554d;line-height:1.66;display:block;margin-top:6px}
    .jk-item{display:grid;grid-template-columns:64px minmax(0,1fr);gap:18px;padding:20px 0;border-bottom:1px solid var(--jk-line)}
    .jk-item:first-of-type{border-top:1px solid var(--jk-line)}
    .jk-code{font:600 10px var(--jk-ui);letter-spacing:.16em;color:var(--jk-saffron);padding-top:5px}

    /* gallery */

    .jk-videos{margin:0;padding:0;list-style:none}
    .jk-videos li{padding:14px 0;border-bottom:1px solid var(--jk-line)}
    .jk-videos a{font:600 15px var(--jk-ui);color:var(--jk-blue)}

    /* Malayalam typography */
    .jk-page.lang-ml .jk-bodytext,.jk-page.lang-ml .jk-intro{font-family:'Noto Serif Malayalam',var(--jk-serif);line-height:1.95}
    .jk-page.lang-ml .jk-bodytext p:first-child::first-letter{font-size:2.5em;line-height:1.1;padding-top:6px}
    .jk-page.lang-ml .jk-name{font-family:'Noto Serif Malayalam',var(--jk-serif);line-height:1.25}
    .jk-page.lang-ml .jk-sectionhead h2{font-family:'Noto Serif Malayalam',var(--jk-serif)}
    .jk-page.lang-ml .jk-ledgerrow span.d,.jk-page.lang-ml .jk-keyfacts dd{font-family:'Noto Serif Malayalam',var(--jk-serif);line-height:1.9}

    /* key facts */
    .jk-keyfacts{margin:26px 0 0;border:1px solid var(--jk-line);background:var(--jk-surface);padding:16px 18px;max-width:620px}
    .jk-keyfacts h2{font:600 10px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:var(--jk-blue);margin:0 0 10px}
    .jk-keyfacts dl{display:grid;grid-template-columns:auto minmax(0,1fr);gap:6px 18px;margin:0}
    .jk-keyfacts dt{font:600 10px var(--jk-ui);letter-spacing:.12em;text-transform:uppercase;color:var(--jk-muted);padding-top:2px}
    .jk-keyfacts dd{margin:0;font:400 13.5px/1.6 var(--jk-ui);color:var(--jk-ink);overflow-wrap:anywhere}
    /* dated career chronology */
    .jk-timeline{margin:0 0 26px;padding:14px 0 18px;border-bottom:1px solid var(--jk-line)}
    .jk-timeline h3{font:600 10px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:var(--jk-muted);margin:0 0 10px}
    .jk-timeline ol{margin:0;padding:0;list-style:none}
    .jk-timeline li{display:grid;grid-template-columns:64px minmax(0,1fr);gap:16px;padding:6px 0;font:400 13.5px var(--jk-ui);color:var(--jk-ink)}
    .jk-timeline li time{font:600 12px var(--jk-ui);color:var(--jk-saffron);font-variant-numeric:tabular-nums}

    /* mobile */
    @media(max-width:1020px){
        .jk-hero.with-rail{grid-template-columns:minmax(0,42fr) minmax(0,58fr)}
        .jk-rail{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
    }
    @media(max-width:720px){
        .jk-page{margin:-18px -14px -48px}
        .jk-hero,.jk-hero.with-rail{grid-template-columns:1fr;gap:30px}
            .jk-rail{grid-template-columns:1fr}
        .jk-engage-grid{grid-template-columns:1fr}
        .jk-urlrow{flex-direction:column;align-items:stretch}
        .jk-urlpill,.jk-copy{justify-content:center;width:100%}
        .jk-section{padding-top:48px}
        .jk-sectionhead{grid-template-columns:auto auto 1fr}
        .jk-tail{grid-column:3}
        .jk-ledgerrow{grid-template-columns:1fr;gap:8px}
        .jk-bodytext{font-size:15.5px}
    }
</style>
@endpush

<div class="jk-page {{ $language === 'ml' ? 'lang-ml' : '' }}">
<div class="jk-wrap">

    <nav class="jk-crumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> / <a href="{{ $listingUrl }}">{{ $listingLabel }}</a> /
        <span aria-current="page">{{ $displayName }}</span>
    </nav>

    <section class="jk-hero">

        <div>
            @if($headline || $profession)
                <p class="jk-eyebrow">{{ $headline ?: $profession }}</p>
            @endif
            <h1 class="name jk-name">@if($nameFirst !== ''){{ $nameFirst }} @endif<span>{{ $nameLast }}</span></h1>
            @if($profession && $headline)
                <p class="jk-org">{{ $profession }}</p>
            @endif
            @if($locationLabel)
                <p class="jk-location">{{ $locationLabel }}</p>
            @endif

            @if($activeEditorial && filled($activeEditorial->summary))
                <p class="jk-intro">{{ $activeEditorial->summary }}</p>
            @endif

            @if($showPermanentUrl)
                <div class="jk-url-label">Permanent profile URL</div>
                <div class="jk-urlrow">
                    <span class="jk-urlpill">{{ $canonicalUrl }}</span>
                    <span style="display:inline-flex;gap:8px">
                        <button type="button" class="jk-copy" data-copy="{{ $canonicalUrl }}">Copy link</button>
                        <button type="button" class="jk-copy" style="background:transparent;color:var(--jk-blue)" data-share="{{ $canonicalUrl }}" data-share-title="{{ $displayName }} — Jannayaks">Share</button>
                    </span>
                    @if($malayalam)
                        <span class="jk-lang" role="navigation" aria-label="Language">
                            <a href="{{ ($__langUrl)('en') }}" lang="en" @if($language === 'en') aria-current="true" @endif>English</a>
                            <a href="{{ ($__langUrl)('ml') }}" lang="ml" @if($language === 'ml') aria-current="true" @endif>മലയാളം</a>
                        </span>
                    @endif
                </div>
            @elseif($malayalam)
                <div class="jk-url-label">Language</div>
                <div class="jk-urlrow">
                    <span class="jk-lang" role="navigation" aria-label="Language">
                        <a href="{{ ($__langUrl)('en') }}" lang="en" @if($language === 'en') aria-current="true" @endif>English</a>
                        <a href="{{ ($__langUrl)('ml') }}" lang="ml" @if($language === 'ml') aria-current="true" @endif>മലയാളം</a>
                    </span>
                </div>
            @endif

            @php($keyFactOffice = $publicOffices->first())
            @php($keyFactYears = $publicOffices->map(fn ($o) => $o->started_on)->filter()->sort()->first())
            @if($keyFactOffice !== null || $keyFactYears !== null)
                <div class="jk-keyfacts">
                    <h2>{{ $language === 'ml' ? 'പ്രധാന വിവരങ്ങൾ' : 'Key Facts' }}</h2>
                    <dl>
                        @if($keyFactOffice !== null && filled($keyFactOffice->office_name))
                            <dt>{{ $language === 'ml' ? 'റോൾ' : 'Role' }}</dt>
                            <dd>{{ $keyFactOffice->office_name }}</dd>
                        @endif
                        @if($keyFactOffice !== null && filled($keyFactOffice->where_location))
                            <dt>{{ $language === 'ml' ? 'സ്ഥലം' : 'Organisation / Place' }}</dt>
                            <dd>{{ $keyFactOffice->where_location }}</dd>
                        @endif
                        @if($keyFactYears !== null)
                            <dt>{{ $language === 'ml' ? 'സജീവമായത്' : 'Active since' }}</dt>
                            <dd>{{ \Illuminate\Support\Str::of((string) $keyFactYears)->before('-')->before(' ').' onwards' }}</dd>
                        @endif
                    </dl>
                </div>
            @endif

            @if($isDemonstration)
                <p class="jk-demo-note">DEMO — Fictional Profile. Fictional demonstration profile — created to show how a Jannayaks profile reads. It does not describe a real person. The photographs on this page are AI-generated illustrations, not photographs of real people.</p>
            @endif
        </div>
    </section>

    @if($activeEditorial && filled($activeEditorial->body))
        <section class="jk-section" id="biography">
            <div class="jk-sectionhead">
                <h2 class="jk-sr">{{ $language === 'ml' ? 'ജീവചരിത്രം' : 'Biography' }}</h2>
                <span class="jk-tail">{{ $profession ?: ($headline ?: 'Public Life') }}</span>
            </div>
            <div class="jk-bodytext">
                @foreach($biographyParagraphs as $pi => $para)
                    <p>{{ $para }}</p>
                    @foreach($insertAfter[$pi] ?? [] as $inlineItem)
                        <figure class="jk-inline-photo">
                            <a href="{{ ($__photoUrl)($inlineItem) }}" target="_blank" rel="noopener" aria-label="View larger">
                                <img src="{{ ($__photoUrl)($inlineItem) }}" alt="{{ $inlineItem->alt_text ?: ($displayName.' — documentary photograph') }}" loading="lazy">
                            </a>
                        </figure>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    @if($publicOffices->isNotEmpty())
        <section class="jk-section" id="career">
            <div class="jk-sectionhead">
                <h2>{{ $language === 'ml' ? 'കരിയറും ഉത്തരവാദിത്തങ്ങളും' : 'Career &amp; Responsibilities' }}</h2>
                <span class="jk-tail">PUBLIC LIFE</span>
            </div>
            @php($datedOffices = $publicOffices->filter(fn ($o) => filled($o->started_on))->sortBy('started_on')->values())
            @if($datedOffices->isNotEmpty())
                <div class="jk-timeline">
                    <h3>{{ $language === 'ml' ? 'കാലക്രമം' : 'Chronology' }}</h3>
                    <ol>
                        @foreach($datedOffices as $datedOffice)
                            <li>
                                <time>{{ \Illuminate\Support\Str::of((string) $datedOffice->started_on)->before('-')->before(' ') }}{{ $datedOffice->is_current ? ' –' : '' }}</time>
                                <span>{{ $datedOffice->office_name }}@if(filled($datedOffice->where_location)) — {{ $datedOffice->where_location }}@endif</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
            <div>
                @foreach($publicOffices as $office)
                    <div class="jk-ledgerrow">
                        <div>
                            <strong>{{ $office->office_name }}</strong>
                            @if(filled($office->where_location))
                                <p class="where">{{ $office->where_location }}</p>
                            @endif
                        </div>
                        @if(filled($office->term_summary))
                            <span class="d">{{ $office->term_summary }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif


    @if($videoLinks->isNotEmpty())
        <section class="jk-section" id="video">
            <div class="jk-sectionhead">
                <h2>Video</h2>
                <span class="jk-tail">EXTERNAL LINKS</span>
            </div>
            <ul class="jk-videos">
                @foreach($videoLinks as $video)
                    <li>
                        <a href="{{ $video->url }}" rel="noopener noreferrer" target="_blank">{{ $video->label ?: 'Watch external video' }}</a>
                        <span style="display:block;margin-top:3px;font:400 12px var(--jk-ui);color:var(--jk-muted)">External link — opens in a new tab</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($showEngagement)
        <section class="jk-engage" id="wellwishers">
            <div class="jk-engage-grid">
                <div class="jk-box">
                    <h2>Show appreciation</h2>
                    <p class="jk-box-sub">No public counts — only {{ $displayName }} will privately see your name and broad location.</p>
                    @if(auth()->check())
                        <div class="jk-reactions">
                            <form method="POST" action="{{ route('profiles.react', $profile->slug) }}">
                                @csrf
                                <input type="hidden" name="reaction" value="like">
                                @php($liked = in_array('like', $viewerReactions))
                                <button type="submit" class="jk-react {{ $liked ? 'on' : '' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">♡ Like</button>
                            </form>
                            <form method="POST" action="{{ route('profiles.react', $profile->slug) }}">
                                @csrf
                                <input type="hidden" name="reaction" value="applaud">
                                @php($applauded = in_array('applaud', $viewerReactions))
                                <button type="submit" class="jk-react {{ $applauded ? 'on' : '' }}" aria-pressed="{{ $applauded ? 'true' : 'false' }}">👏 Applaud</button>
                            </form>
                        </div>
                        <p class="jk-privacy">Your name and broad location may be visible to the profile owner. Your phone number and email are never shared because of a reaction.</p>
                    @else
                        <a class="jk-react-login" href="{{ route('login', ['return' => url()->current()]) }}">Sign in to Like or Applaud →</a>
                        <p class="jk-privacy">A lightweight sign-in (Jannayaks account or Google) is required, so profile owners see genuine well-wishers only.</p>
                    @endif
                </div>

                <div class="jk-box">
                    <h2>Contact</h2>
                    <p class="jk-box-sub">Send a brief message to {{ $displayName }}. Your details are shared only with the profile owner — never displayed publicly.</p>
                    @if(session('contact_status') === 'sent')
                        <p class="jk-status">Thank you. Your message has been passed on to {{ $displayName }}.</p>
                    @endif
                    @if($errors->any())
                        <p class="jk-status err">{{ $errors->first('contact') ?: 'Please review the highlighted fields and try again.' }}</p>
                    @endif
                    <p class="jk-privacy jk-disclosure" style="margin:0 0 14px;font-size:12px;line-height:1.6">
                        Prepared from information supplied by the profile owner. Not independently verified. Please check the facts before relying on them.<br>
                        <span lang="ml">പ്രൊഫൈൽ ഉടമ നേരിട്ട് നൽകിയ വിവരങ്ങളുടെ അടിസ്ഥാനത്തിൽ തയ്യാറാക്കിയത്. വിവരങ്ങൾ സ്വതന്ത്രമായി സ്ഥിരീകരിച്ചിട്ടില്ല. ആശ്രയിക്കുന്നതിന് മുമ്പ് അവയുടെ കൃത്യത പരിശോധിക്കുക.</span>
                    </p>
                    <form method="POST" action="{{ route('profiles.contact', $profile->slug) }}">
                        @csrf
                        <input type="text" name="honey_bot" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
                        <div class="jk-field">
                            <label for="visitor_name">Your name</label>
                            <input id="visitor_name" name="visitor_name" maxlength="120" required value="{{ old('visitor_name') }}">
                        </div>
                        <div class="jk-field">
                            <label for="visitor_mobile">Your mobile number</label>
                            <input id="visitor_mobile" name="visitor_mobile" maxlength="20" inputmode="tel" value="{{ old('visitor_mobile') }}">
                        </div>
                        <div class="jk-field">
                            <label for="message">Message / reason for contact</label>
                            <textarea id="message" name="message" maxlength="1000" required>{{ old('message') }}</textarea>
                        </div>
                        <button type="submit" class="jk-send">Send message</button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    @if($showBackLink)
        <p style="margin-top:56px"><a href="{{ $listingUrl }}" style="font:600 12px var(--jk-ui);letter-spacing:.08em;color:var(--jk-blue)">← Back to {{ $listingLabel }}</a></p>
    @endif

    @if(filled($referenceCode ?? null))
        <p style="margin:14px 0 0;font:500 10px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:#82907f">Profile reference · {{ $referenceCode }}</p>
    @endif
</div>
</div>

@push('scripts')
<script>
(function(){
    var copied = 'Link copied';
    function toast(msg){
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:#214d68;color:#fff;padding:10px 18px;font:500 13px sans-serif;z-index:60;opacity:0;transition:opacity .2s';
        document.body.appendChild(t);
        requestAnimationFrame(function(){ t.style.opacity = '1'; });
        setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 300); }, 1800);
    }
    function copyText(text){
        if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text).then(function(){ toast(copied); });
        var ta = document.createElement('textarea');
        ta.value = text; ta.setAttribute('readonly',''); ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); toast(copied); } catch(e){}
        ta.remove();
        return Promise.resolve();
    }
    document.querySelectorAll('[data-copy]').forEach(function(b){
        b.addEventListener('click', function(){ copyText(b.dataset.copy); });
    });
    document.querySelectorAll('[data-share]').forEach(function(b){
        b.addEventListener('click', function(){
            if (navigator.share) { navigator.share({ title: b.dataset.shareTitle, url: b.dataset.share }).catch(function(){}); }
            else { copyText(b.dataset.share); }
        });
    });
})();
</script>
@endpush
