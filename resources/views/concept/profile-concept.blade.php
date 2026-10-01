<!DOCTYPE html>
<html lang="{{ $lang === 'ml' ? 'ml' : 'en' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $specimen[$lang]['name'] }} — {{ $lang === 'ml' ? 'ജന്നയക്സ്' : 'Jannayaks' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,500&family=Inter:wght@400;500;600&family=Anek+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ============================================================
   JANNAYAKS — PROFILE CONCEPT · ISOLATED DESIGN EXPLORATION
   A fresh editorial design. No dependency on any production
   view, layout, partial or stylesheet. Self-contained by design.
   ============================================================ */
:root{
  --paper:#F6F2EA;
  --paper-deep:#EFE9DD;
  --ink:#1D1A15;
  --ink-soft:#57503F;
  --mute:#8A8271;
  --hair:#D9D1BF;
  --hair-soft:#E5DFD1;
  --oxblood:#6E2A26;
  --oxblood-deep:#54201D;
  --gold:#A98A4E;
  --serif-en:'Fraunces',Georgia,serif;
  --ui:'Inter',system-ui,sans-serif;
  --serif-ml:'Anek Malayalam','Fraunces',serif;
  --measure:64ch;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  background:var(--paper);
  color:var(--ink);
  font-family:var(--serif-en);
  font-size:17px;
  line-height:1.72;
  -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility;
  background-image:
    repeating-linear-gradient(0deg, rgba(29,26,21,.014) 0 1px, transparent 1px 3px);
}
::selection{background:var(--oxblood);color:var(--paper)}

/* ————— top bar ————— */
.topbar{
  position:sticky;top:0;z-index:40;
  background:color-mix(in srgb, var(--paper) 88%, transparent);
  backdrop-filter:blur(10px);
  border-bottom:1px solid var(--hair);
}
.topbar-rule{position:absolute;left:0;bottom:-1px;height:1px;width:0;background:var(--oxblood);transition:width .12s linear}
.topbar-in{
  max-width:1180px;margin:0 auto;padding:0 clamp(18px,4vw,44px);
  height:56px;display:flex;align-items:center;gap:26px;
}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--ink);font-family:var(--ui);font-size:12.5px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;white-space:nowrap}
.brand .jeeb{font-family:var(--serif-ml);font-size:19px;font-weight:600;color:var(--oxblood);letter-spacing:0;text-transform:none}
.concept-nav{display:flex;gap:4px;margin-left:auto;overflow-x:auto;scrollbar-width:none}
.concept-nav::-webkit-scrollbar{display:none}
.concept-nav a{
  font-family:var(--ui);font-size:11.5px;font-weight:500;letter-spacing:.09em;text-transform:uppercase;
  color:var(--ink-soft);text-decoration:none;padding:7px 12px;border-radius:2px;white-space:nowrap;
  display:flex;align-items:center;gap:7px;transition:color .15s;
}
.concept-nav a::before{content:"";width:4px;height:4px;border-radius:50%;background:transparent;transition:background .15s}
.concept-nav a:hover{color:var(--ink)}
.concept-nav a.active{color:var(--oxblood-deep)}
.concept-nav a.active::before{background:var(--oxblood)}
.lang-toggle{font-family:var(--ui);font-size:11px;font-weight:600;letter-spacing:.08em;color:var(--ink-soft);text-decoration:none;border:1px solid var(--hair);padding:5px 10px;border-radius:2px;white-space:nowrap;transition:all .15s}
.lang-toggle:hover{border-color:var(--oxblood);color:var(--oxblood-deep)}

/* ————— hero ————— */
.hero{
  max-width:1180px;margin:0 auto;
  padding:clamp(44px,7vh,84px) clamp(18px,4vw,44px) clamp(36px,5vh,60px);
  display:grid;
  grid-template-columns:minmax(0,5fr) minmax(0,7fr);
  gap:clamp(28px,5vw,72px);
  align-items:end;
}
.portrait{position:relative}
.portrait-frame{
  position:relative;aspect-ratio:3/4;
  border:1px solid var(--ink);
  outline:1px solid var(--hair);
  outline-offset:8px;
  overflow:hidden;
  background:var(--paper-deep);
}
.portrait-frame::after{ /* archival corner ticks */
  content:"";position:absolute;inset:10px;
  background:
    linear-gradient(var(--ink),var(--ink)) top left/14px 1px no-repeat,
    linear-gradient(var(--ink),var(--ink)) top left/1px 14px no-repeat,
    linear-gradient(var(--ink),var(--ink)) top right/14px 1px no-repeat,
    linear-gradient(var(--ink),var(--ink)) top right/1px 14px no-repeat,
    linear-gradient(var(--ink),var(--ink)) bottom left/14px 1px no-repeat,
    linear-gradient(var(--ink),var(--ink)) bottom left/1px 14px no-repeat,
    linear-gradient(var(--ink),var(--ink)) bottom right/14px 1px no-repeat,
    linear-gradient(var(--ink),var(--ink)) bottom right/1px 14px no-repeat;
  opacity:.55;pointer-events:none;
}
.plate{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;overflow:hidden}
.plate-tone{position:absolute;inset:0;
  background:
    radial-gradient(120% 90% at 30% 20%, rgba(169,138,78,.20), transparent 60%),
    radial-gradient(100% 100% at 70% 85%, rgba(110,42,38,.16), transparent 55%),
    linear-gradient(160deg,#D8CEBB 0%,#C7BBA4 45%,#B4A88F 100%);
}
.plate::before{ /* fine archival hatch */
  content:"";position:absolute;inset:0;opacity:.35;
  background:repeating-linear-gradient(45deg, rgba(29,26,21,.05) 0 1px, transparent 1px 7px);
}
.plate-mark{
  position:relative;font-family:var(--serif-en);font-weight:600;font-size:clamp(64px,9vw,120px);
  color:rgba(29,26,21,.16);letter-spacing:.04em;user-select:none;
}
.portrait figcaption{
  margin-top:14px;display:flex;justify-content:space-between;gap:12px;
  font-family:var(--ui);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);
}
.portrait figcaption .no{color:var(--gold)}

.identity{padding-bottom:6px}
.eyebrow{
  font-family:var(--ui);font-size:11.5px;font-weight:600;letter-spacing:.22em;text-transform:uppercase;
  color:var(--oxblood);display:flex;align-items:center;gap:14px;
}
.eyebrow::after{content:"";height:1px;flex:0 0 56px;background:var(--oxblood);opacity:.5}
.name{
  font-weight:600;
  font-size:clamp(46px,6.2vw,86px);
  line-height:1.02;
  letter-spacing:-.015em;
  margin:18px 0 4px;
  font-variation-settings:"opsz" 144;
}
.name .fin{color:var(--oxblood)}
.name-sub{font-family:var(--ui);font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);font-weight:500;margin-bottom:22px}
.org{font-size:clamp(17px,1.6vw,20px);color:var(--ink-soft);font-style:italic;max-width:44ch}
.intro{
  margin-top:26px;padding-left:22px;border-left:2px solid var(--gold);
  font-size:clamp(19px,1.9vw,23px);line-height:1.55;font-weight:400;color:var(--ink);
  max-width:56ch;
}
.url-row{margin-top:30px;display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.url-label{font-family:var(--ui);font-size:10px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--mute);width:100%}
.url-pill{
  display:inline-flex;align-items:center;gap:14px;
  border:1px solid var(--ink);border-radius:2px;background:transparent;
  padding:9px 10px 9px 16px;font-family:var(--ui);font-size:13px;letter-spacing:.02em;color:var(--ink);
}
.url-pill .dot{color:var(--gold)}
.copy-btn{
  border:1px solid var(--ink);background:var(--ink);color:var(--paper);
  font-family:var(--ui);font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  padding:9px 14px;border-radius:2px;cursor:pointer;display:inline-flex;gap:8px;align-items:center;
  transition:background .15s;
}
.copy-btn:hover{background:var(--oxblood-deep);border-color:var(--oxblood-deep)}

/* ————— sections ————— */
.section{max-width:1180px;margin:0 auto;padding:clamp(40px,6vh,72px) clamp(18px,4vw,44px) 8px}
.section-head{display:flex;align-items:baseline;gap:18px;border-bottom:1px solid var(--ink);padding-bottom:14px;margin-bottom:clamp(28px,4vh,44px)}
.section-head .num{font-family:var(--ui);font-size:11px;font-weight:600;letter-spacing:.2em;color:var(--gold)}
.section-head h2{font-weight:600;font-size:clamp(21px,2.4vw,29px);letter-spacing:-.01em}
.section-head .tail{margin-left:auto;font-family:var(--ui);font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);font-weight:500;white-space:nowrap}

.biography{max-width:var(--measure);margin:0 auto}
.biography p{margin-bottom:1.35em}
.biography p.lead::first-letter{
  font-weight:600;font-size:3.35em;float:left;line-height:.82;
  padding:.09em .12em 0 0;color:var(--oxblood);font-variation-settings:"opsz" 144;
}
.pull{
  margin:2.4em 0;padding:6px 0 6px 26px;border-left:2px solid var(--oxblood);
  font-size:clamp(20px,2vw,24px);line-height:1.5;font-weight:500;font-style:italic;color:var(--oxblood-deep);
}

/* full-width visual moment */
.moment{max-width:1180px;margin:clamp(40px,6vh,64px) auto;padding:0 clamp(18px,4vw,44px)}
.moment-frame{position:relative;aspect-ratio:21/9;border:1px solid var(--ink);overflow:hidden;background:var(--paper-deep)}
.moment-frame .plate-mark{font-size:clamp(40px,5vw,84px)}
.moment figcaption{margin-top:12px;display:flex;justify-content:space-between;font-family:var(--ui);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--mute)}

/* career — ledger + rail */
.career-grid{display:grid;grid-template-columns:minmax(0,7fr) minmax(0,5fr);gap:clamp(32px,5vw,72px)}
.ledger{border-top:1px solid var(--hair)}
.ledger-row{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,4fr);gap:20px;padding:20px 0;border-bottom:1px solid var(--hair-soft)}
.ledger-row dt{font-family:var(--ui);font-size:12px;font-weight:600;letter-spacing:.06em;color:var(--oxblood-deep);text-transform:uppercase;padding-top:4px}
.ledger-row dd{font-size:16px;color:var(--ink-soft);line-height:1.66}
.edu-note{margin-top:26px;padding:18px 20px;background:var(--paper-deep);border-left:2px solid var(--gold);font-size:15.5px;color:var(--ink-soft)}
.edu-note strong{display:block;font-family:var(--ui);font-size:10.5px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);margin-bottom:6px}

.rail{position:relative;padding-left:26px}
.rail::before{content:"";position:absolute;left:5px;top:6px;bottom:6px;width:1px;background:var(--hair)}
.rail-head{font-family:var(--ui);font-size:10.5px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--mute);margin-bottom:18px}
.rail-item{position:relative;padding:0 0 22px}
.rail-item::before{content:"";position:absolute;left:-26px;top:7px;width:11px;height:11px;border-radius:50%;border:1px solid var(--oxblood);background:var(--paper)}
.rail-item:first-of-type::before{background:var(--oxblood)}
.rail-item .t{font-size:16.5px;font-weight:500;line-height:1.45}

/* contributions */
.contrib-list{max-width:var(--measure);margin:0 auto}
.contrib{display:grid;grid-template-columns:52px minmax(0,1fr);gap:18px;padding:20px 0;border-bottom:1px solid var(--hair-soft)}
.contrib:first-child{border-top:1px solid var(--hair)}
.contrib .glyph{font-family:var(--ui);font-size:11px;font-weight:600;color:var(--gold);letter-spacing:.1em;padding-top:5px}
.contrib .body{font-size:16.5px;color:var(--ink-soft);line-height:1.66}
.contrib .body b{color:var(--ink);font-weight:600}

/* achievements */
.ach-list{max-width:var(--measure);margin:0 auto;counter-reset:ach}
.ach{display:grid;grid-template-columns:64px minmax(0,1fr);align-items:baseline;padding:16px 0;border-bottom:1px solid var(--hair-soft)}
.ach:first-child{border-top:1px solid var(--hair)}
.ach::before{counter-increment:ach;content:counter(ach,decimal-leading-zero);font-family:var(--ui);font-size:11px;font-weight:600;letter-spacing:.12em;color:var(--oxblood)}
.ach .body{font-size:17px}

/* gallery — asymmetric editorial */
.gallery-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:clamp(14px,2vw,22px)}
.g-item{position:relative;border:1px solid var(--ink);overflow:hidden;background:var(--paper-deep)}
.g-item .plate-mark{font-size:clamp(34px,3.6vw,58px)}
.g-wide{grid-column:1/8;aspect-ratio:16/10}
.g-tall{grid-column:8/13;aspect-ratio:4/5;margin-top:clamp(22px,4vw,54px)}
.g-small{grid-column:1/5;aspect-ratio:5/4;margin-top:calc(-1 * clamp(10px,2vw,28px))}
.g-note{grid-column:5/13;align-self:end;padding:6px 0 14px}
.g-note p{font-size:16px;color:var(--ink-soft);font-style:italic;max-width:52ch}
.g-cap{display:block;margin-top:10px;font-family:var(--ui);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--mute)}

/* footer */
.concept-foot{margin-top:clamp(48px,7vh,84px);border-top:1px solid var(--ink)}
.foot-in{max-width:1180px;margin:0 auto;padding:34px clamp(18px,4vw,44px) 60px;display:flex;flex-wrap:wrap;gap:24px;align-items:center}
.foot-in .brand{margin-right:auto}
.share-btn{
  border:1px solid var(--ink);background:transparent;color:var(--ink);
  font-family:var(--ui);font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  padding:9px 14px;border-radius:2px;cursor:pointer;transition:all .15s;
}
.share-btn:hover{background:var(--ink);color:var(--paper)}
.specimen-note{width:100%;font-family:var(--ui);font-size:10.5px;letter-spacing:.1em;color:var(--mute);text-transform:uppercase}

/* toast */
.toast{
  position:fixed;left:50%;bottom:26px;transform:translate(-50%,14px);opacity:0;pointer-events:none;
  background:var(--ink);color:var(--paper);font-family:var(--ui);font-size:12px;font-weight:500;letter-spacing:.06em;
  padding:10px 18px;border-radius:2px;transition:all .22s;z-index:60;
}
.toast.show{opacity:1;transform:translate(-50%,0)}

/* ————— Malayalam ————— */
html.lang-ml body{font-family:var(--serif-ml);line-height:1.92}
html.lang-ml .topbar-in,html.lang-ml .concept-nav a,html.lang-ml .lang-toggle,html.lang-ml .brand{font-family:var(--ui)}
html.lang-ml .concept-nav a{letter-spacing:0;text-transform:none;font-size:13px}
html.lang-ml .lang-toggle,html.lang-ml .brand{letter-spacing:.04em;text-transform:none}
html.lang-ml .eyebrow,html.lang-ml .section-head .tail,html.lang-ml .portrait figcaption,
html.lang-ml .url-label,html.lang-ml .rail-head,html.lang-ml .g-cap,html.lang-ml .specimen-note,
html.lang-ml .edu-note strong{letter-spacing:.02em;text-transform:none;font-size:12.5px}
html.lang-ml .name{font-size:clamp(40px,5.6vw,74px);line-height:1.22;letter-spacing:0;font-family:var(--serif-ml);font-weight:600}
html.lang-ml .section-head h2{font-family:var(--serif-ml);font-size:clamp(20px,2.2vw,26px);font-weight:600}
html.lang-ml .biography,html.lang-ml .contrib .body,html.lang-ml .ledger-row dd{max-width:58ch}
html.lang-ml .biography p.lead::first-letter{font-size:2.6em;line-height:1.1;padding-top:.08em}
html.lang-ml .pull{font-style:normal;line-height:1.72}
html.lang-ml .intro{line-height:1.8}
html.lang-ml .plate-mark{font-family:var(--serif-ml)}

/* ————— mobile ————— */
@media (max-width:880px){
  .hero{grid-template-columns:1fr;gap:34px;align-items:start;padding-top:34px}
  .portrait{max-width:340px;margin:0 auto}
  .identity{text-align:left}
  .name{font-size:clamp(40px,11vw,56px)}
  .intro{font-size:18.5px}
  .career-grid{grid-template-columns:1fr}
  .moment-frame{aspect-ratio:4/3}
  .gallery-grid{display:flex;gap:14px;overflow-x:auto;scroll-snap-type:x mandatory;margin:0 calc(-1 * clamp(18px,4vw,44px));padding:0 clamp(18px,4vw,44px) 6px;scrollbar-width:none}
  .gallery-grid::-webkit-scrollbar{display:none}
  .g-item,.g-note{grid-column:auto;margin-top:0;flex:0 0 78%;scroll-snap-align:center}
  .g-wide{aspect-ratio:4/3}
  .g-tall{flex-basis:64%;aspect-ratio:3/4}
  .g-small{flex-basis:56%;aspect-ratio:1/1}
  .g-note{flex-basis:86%}
  .topbar-in{gap:14px}
  .concept-nav{margin-left:auto}
  .contrib,.ach{grid-template-columns:40px minmax(0,1fr)}
  .ledger-row{grid-template-columns:1fr;gap:6px;padding:16px 0}
  .pull{font-size:19px}
}
@media (max-width:480px){
  body{font-size:16px}
  .url-pill{width:100%;justify-content:space-between}
  .copy-btn{width:100%;justify-content:center;margin-top:2px}
  .url-row{gap:8px}
}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
</style>
</head>
@php($d = $specimen[$lang])
@php($labels = $d['labels'])
<body>
<header class="topbar">
  <div class="topbar-in">
    <a class="brand" href="/"><span class="jeeb">ജ</span>{{ $lang === 'ml' ? 'ജന്നയക്സ്' : 'Jannayaks' }}</a>
    <nav class="concept-nav" aria-label="profile sections">
      <a href="#biography">{{ $labels['biography'] }}</a>
      <a href="#career">{{ $labels['career'] }}</a>
      <a href="#contributions">{{ $labels['contributions'] }}</a>
      <a href="#achievements">{{ $labels['achievements'] }}</a>
      <a href="#gallery">{{ $labels['gallery'] }}</a>
    </nav>
    <a class="lang-toggle" href="{{ $lang === 'ml' ? '/profile-concept' : '/profile-concept?lang=ml' }}">{{ $lang === 'ml' ? 'EN' : 'മലയാളം' }}</a>
  </div>
  <div class="topbar-rule" id="progress"></div>
</header>

<main>
  <!-- ————— hero ————— -->
  <section class="hero">
    <figure class="portrait">
      <div class="portrait-frame">
        <div class="plate"><div class="plate-tone"></div><span class="plate-mark">{{ $lang === 'ml' ? 'അവ്' : 'AV' }}</span></div>
      </div>
      <figcaption><span>{{ $labels['portrait_caption'] }}</span><span class="no">PL · I</span></figcaption>
    </figure>
    <div class="identity">
      <p class="eyebrow">{{ $d['credentials'] }}</p>
      @php($nameWords = preg_split('/\s+/u', trim($d['name']), -1, PREG_SPLIT_NO_EMPTY))
      @php($lastWord = array_pop($nameWords))
      @php($firstWords = implode(' ', $nameWords))
      <h1 class="name">@if($firstWords !== ''){{ $firstWords }} @endif<span class="fin">{{ $lastWord }}</span></h1>
      @if($lang === 'ml')<p class="name-sub">{{ $d['name_sub'] }}</p>@endif
      <p class="org">{{ $d['organisation'] }}</p>
      <p class="intro">{{ $d['intro'] }}</p>
      <div class="url-row">
        <span class="url-label">{{ $labels['url_label'] }}</span>
        <span class="url-pill"><span>jannayaks.in/<span class="dot">·</span>{{ $specimen['slug'] }}</span></span>
        <button class="copy-btn" id="copyTop" data-url="https://jannayaks.in/{{ $specimen['slug'] }}">{{ $labels['copy'] }}</button>
      </div>
    </div>
  </section>

  <!-- ————— I · biography ————— -->
  <section class="section" id="biography">
    <div class="section-head">
      <span class="num">I</span>
      <h2>{{ $labels['biography'] }}</h2>
      <span class="tail">{{ $d['field'] }}</span>
    </div>
    <div class="biography">
      @foreach($d['biography'] as $i => $para)
        @if($i === 3)
          <aside class="pull">{{ $lang === 'en' ? 'Administration is not simply about choosing a solution; it is also about explaining a decision to people who may have legitimate reasons for disagreeing with it.' : 'ഒരു തീരുമാനം എടുക്കുന്നത് മാത്രം പോരാ; അതിനെ എതിര്ക്കുന്ന ആളുകൾക്ക് പോലും അതിന്റെ കാരണം വിശദീകരിക്കേണ്ടത് പൊതുഭരണത്തിന്റെ ഭാഗമാണെന്ന് അദ്ദേഹം കരുതുന്നു.' }}</aside>
        @endif
        @if($i === 6)
          </div>
          <figure class="moment">
            <div class="moment-frame">
              <div class="plate"><div class="plate-tone"></div><span class="plate-mark">{{ $lang === 'ml' ? 'അവ്' : 'AV' }}</span></div>
            </div>
            <figcaption><span>{{ $labels['plate_ii'] }}</span><span class="no" style="color:var(--gold)">PL · II</span></figcaption>
          </figure>
          <div class="biography" style="margin-top:clamp(30px,5vh,48px)">
        @endif
        <p {{ $i === 0 ? 'class="lead"' : '' }}>{{ $para }}</p>
      @endforeach
      @foreach($d['closing'] as $para)
        <p>{{ $para }}</p>
      @endforeach
    </div>
  </section>

  <!-- ————— II · career ————— -->
  <section class="section" id="career">
    <div class="section-head">
      <span class="num">II</span>
      <h2>{{ $labels['career'] }}</h2>
      <span class="tail">{{ $d['field'] }}</span>
    </div>
    <div class="career-grid">
      <div>
        <dl class="ledger">
          @foreach($d['career'] as [$when, $what])
            <div class="ledger-row">
              <dt>{{ $when }}</dt>
              <dd>{{ $what }}</dd>
            </div>
          @endforeach
        </dl>
        <div class="edu-note">
          <strong>{{ $labels['education'] }}</strong>
          {{ $labels['education_body'] }}
        </div>
      </div>
      <aside class="rail">
        <p class="rail-head">{{ $labels['milestones'] }}</p>
        @foreach($d['milestones'] as $m)
          <div class="rail-item"><p class="t">{{ $m }}</p></div>
        @endforeach
      </aside>
    </div>
  </section>

  <!-- ————— III · contributions ————— -->
  <section class="section" id="contributions">
    <div class="section-head">
      <span class="num">III</span>
      <h2>{{ $labels['contributions'] }}</h2>
      <span class="tail">{{ $d['field'] }}</span>
    </div>
    <div class="contrib-list">
      @foreach($d['contributions'] as $i => $c)
        @php($title = is_array($c) ? $c[0] : null)
        @php($body = is_array($c) ? $c[1] : $c)
        <div class="contrib">
          <span class="glyph">C{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
          <p class="body">@if($title)<b>{{ $title }} — </b>@endif{{ $body }}</p>
        </div>
      @endforeach
    </div>
  </section>

  <!-- ————— IV · achievements ————— -->
  <section class="section" id="achievements">
    <div class="section-head">
      <span class="num">IV</span>
      <h2>{{ $labels['achievements'] }}</h2>
      <span class="tail">{{ $d['field'] }}</span>
    </div>
    <div class="ach-list">
      @foreach($d['achievements'] as $a)
        <div class="ach"><p class="body">{{ $a }}</p></div>
      @endforeach
    </div>
  </section>

  <!-- ————— V · gallery ————— -->
  <section class="section" id="gallery">
    <div class="section-head">
      <span class="num">V</span>
      <h2>{{ $labels['gallery'] }}</h2>
      <span class="tail">{{ $lang === 'ml' ? 'ആർക്കൈവ്' : 'Archive plates' }}</span>
    </div>
    <div class="gallery-grid">
      <figure class="g-item g-wide">
        <div class="plate"><div class="plate-tone"></div><span class="plate-mark">{{ $lang === 'ml' ? 'അവ്' : 'AV' }}</span></div>
        <figcaption class="g-cap" style="position:absolute;bottom:0;left:0;right:0;padding:8px 12px;background:linear-gradient(transparent,rgba(29,26,21,.55));color:var(--paper);letter-spacing:.12em">{{ $labels['plate_iii'] }}</figcaption>
      </figure>
      <figure class="g-item g-tall">
        <div class="plate"><div class="plate-tone"></div><span class="plate-mark">{{ $lang === 'ml' ? 'അവ്' : 'AV' }}</span></div>
      </figure>
      <figure class="g-item g-small">
        <div class="plate"><div class="plate-tone"></div><span class="plate-mark">{{ $lang === 'ml' ? 'അവ്' : 'AV' }}</span></div>
      </figure>
      <div class="g-note">
        <p>{{ $lang === 'en' ? 'Photographs are held as archival plates — documented, captioned and preserved alongside the written record rather than arranged as decoration.' : 'ഛായാചിത്രങ്ങൾ ആർക്കൈവ് പ്ലേറ്റുകളായി സൂക്ഷിക്കുന്നു — അലങ്കാരമായല്ല, രേഖപ്പെടുത്തിയ ദൃശ്യങ്ങളായി.' }}</p>
        <span class="g-cap">{{ $labels['plate_i'] }} · {{ $labels['plate_iv'] }}</span>
      </div>
    </div>
  </section>
</main>

<footer class="concept-foot">
  <div class="foot-in">
    <a class="brand" href="/"><span class="jeeb">ജ</span>{{ $lang === 'ml' ? 'ജന്നയക്സ്' : 'Jannayaks' }}</a>
    <span class="url-pill" style="border-color:var(--hair)"><span>jannayaks.in/<span class="dot">·</span>{{ $specimen['slug'] }}</span></span>
    <button class="copy-btn" id="copyFoot" data-url="https://jannayaks.in/{{ $specimen['slug'] }}">{{ $labels['copy'] }}</button>
    <button class="share-btn" id="shareBtn" data-url="https://jannayaks.in/{{ $specimen['slug'] }}" data-title="{{ $specimen['en']['name'] }} — Jannayaks">{{ $labels['share'] }}</button>
    <span class="specimen-note">{{ $labels['specimen'] }}</span>
  </div>
</footer>

<div class="toast" id="toast">{{ $labels['copied'] }}</div>

<script>
(function(){
  var toast = document.getElementById('toast'), toastTimer;
  function showToast(msg){ toast.textContent = msg; toast.classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(function(){ toast.classList.remove('show'); }, 1800); }
  function copyText(text, done){
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done, function(){ legacyCopy(text, done); }); }
    else { legacyCopy(text, done); }
  }
  function legacyCopy(text, done){
    var ta = document.createElement('textarea'); ta.value = text; ta.setAttribute('readonly',''); ta.style.position='fixed'; ta.style.opacity='0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); done(); } catch(e){}
    document.body.removeChild(ta);
  }
  var copiedMsg = {{ json_encode($labels['copied']) }};
  ['copyTop','copyFoot'].forEach(function(id){
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', function(){ copyText(b.dataset.url, function(){ showToast(copiedMsg); }); });
  });
  var share = document.getElementById('shareBtn');
  if (share) share.addEventListener('click', function(){
    if (navigator.share) { navigator.share({ title: share.dataset.title, url: share.dataset.url }).catch(function(){}); }
    else copyText(share.dataset.url, function(){ showToast(copiedMsg); });
  });

  /* scroll progress */
  var bar = document.getElementById('progress');
  function onScroll(){
    var h = document.documentElement;
    var max = h.scrollHeight - h.clientHeight;
    bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%';
  }
  document.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  /* scroll-spy */
  var links = Array.prototype.slice.call(document.querySelectorAll('.concept-nav a'));
  var map = {};
  links.forEach(function(a){ map[a.getAttribute('href').slice(1)] = a; });
  var targets = links.map(function(a){ return document.getElementById(a.getAttribute('href').slice(1)); }).filter(Boolean);
  if ('IntersectionObserver' in window) {
    var current = null;
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if (en.isIntersecting) {
          if (current) current.classList.remove('active');
          current = map[en.target.id]; if (current) current.classList.add('active');
        }
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    targets.forEach(function(t){ io.observe(t); });
  }
})();
</script>
</body>
</html>
