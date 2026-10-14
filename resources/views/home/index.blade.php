<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
<link rel="icon" href="{{ asset('branding/favicon.ico') }}">
<link rel="preload" as="image" href="{{ asset('images/jannayaks-hero.jpeg') }}" fetchpriority="high">
<title>Jannayaks™ — Kerala's Public Presence Platform</title>
@php($schemaJson = app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->websiteGraph(
    'Jannayaks is a digital biographical archive — verified profiles and In Memoriam records documenting the lives, work and public contribution of people’s leaders.',
    url('/search'),
)))
@include('partials.schema-ld', ['json' => $schemaJson])
@include('partials.seo-meta', [
    'title' => 'Jannayaks™ — A Digital Gallery of People’s Leaders',
    'description' => 'Jannayaks is a digital biographical archive — verified profiles and In Memoriam records documenting the lives, work and public contribution of people’s leaders.',
    'canonical' => url('/'),
])
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400&family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=DM+Sans:wght@300;400;500;600&family=Noto+Serif+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  /* Approved Jannayaks homepage palette: soft sage ground, near-white warm
     surfaces, greenish-charcoal text, muted Ashoka-Chakra blue primary,
     restrained saffron secondary, subtle hairline borders. */
  --navy:#214d68;--saffron:#C0762E;--white:#FDFDFB;--off:#F3F8F0;
  --gray:#6f8075;--light:#eaf2e7;--charcoal:#1f2924;--border:#d3ddd1;
  --gold:#A98A4E;--goldbg:#F7F3E8;--greenbg:#EDF3EA;--green:#2F6B4F;
  --fd:'Fraunces','Cormorant Garamond',Georgia,serif;--fb:'DM Sans',sans-serif;--fm:'Noto Serif Malayalam',serif;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{background:var(--off);color:var(--charcoal);font-family:var(--fb);-webkit-font-smoothing:antialiased;position:relative;overflow-x:hidden;}
.container{width:100%;max-width:1100px;margin:0 auto;padding:0 22px;}
.ml{font-family:var(--fm);display:block;font-size:.85em;color:var(--gray);margin-top:2px;}
/* NAV */
nav{background:var(--white);border-bottom:2px solid var(--saffron);position:sticky;top:0;z-index:100;box-shadow:0 1px 12px rgba(0,0,0,.06);}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:64px;padding:0 22px;max-width:1400px;margin:0 auto;gap:12px;}
.nav-logo{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.nav-logo img{height:58px;width:auto;display:block;}
.nav-menu-btn{display:none;align-items:center;justify-content:center;width:44px;height:44px;border:1.5px solid var(--border);border-radius:6px;background:white;color:var(--navy);font-size:20px;line-height:1;cursor:pointer;font-family:var(--fb);flex-shrink:0;}
.nav-menu-btn:hover{border-color:var(--navy);}
.home-actions{display:flex;flex-wrap:wrap;gap:18px;justify-content:center;margin-top:22px;}
.home-action{font-size:14px;font-weight:600;color:var(--navy);text-decoration:none;border-bottom:1.5px solid transparent;padding-bottom:2px;transition:color .2s,border-color .2s;}
.home-action:hover{color:var(--saffron);border-bottom-color:var(--saffron);}
.ep-section{padding:40px 22px 8px;background:transparent;}
.nav-links{display:none;list-style:none;gap:28px;}
.nav-links a{font-size:13px;color:var(--gray);text-decoration:none;font-weight:500;transition:color .2s;}
.nav-links a:hover{color:var(--navy);}
.nav-links .nav-login-mobile{display:none;}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.translate-btn{background:transparent;border:1px solid var(--border);color:var(--gray);padding:6px 12px;border-radius:4px;font-size:12px;font-family:var(--fb);cursor:pointer;display:flex;align-items:center;gap:5px;transition:all .2s;min-height:40px;}
.translate-btn:hover{border-color:var(--navy);color:var(--navy);}
.nav-login{background:transparent;border:1.5px solid var(--navy);color:var(--navy);padding:7px 16px;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;min-height:40px;}
.nav-login:hover{background:var(--navy);color:white;}
.nav-cta{background:var(--saffron);color:white;border:none;padding:8px 18px;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;min-height:40px;}
.nav-cta:hover{background:#9d5e22;}
/* HERO */
.hero{background:var(--off);padding:56px 22px 48px;text-align:center;border-bottom:1px solid var(--border);position:relative;overflow:hidden;}
.hero::before{display:none;}
.hero::after{display:none;}
.hero-main-heading{text-align:center;max-width:900px;margin:0 auto 28px;position:relative;}
/* Kerala launch is Malayalam-first: Malayalam is the primary hero heading. */
.hero-main-heading h1.hero-ml-primary{font-family:var(--fm);font-size:clamp(35px,calc(6.4vw - 1px),63px);line-height:1.32;font-weight:700;color:var(--navy);margin:0 0 14px;overflow-wrap:anywhere;}
.hero-en-secondary{display:block;font-family:var(--fd);font-size:clamp(17px,2.3vw,25px);line-height:1.4;font-weight:400;color:#52758a;margin:0;overflow-wrap:anywhere;}
.hero-intro{max-width:760px;margin:0 auto 0;position:relative;}
.hero-intro p{font-size:17px;line-height:1.65;font-weight:300;color:var(--charcoal);}
.hero-intro .hero-ml{font-family:var(--fm);font-size:14px;line-height:1.7;color:var(--gray);margin-top:8px;display:block;}
.hero-banner{position:relative;z-index:1;max-width:800px;margin:0 auto;}
.hero-banner img{display:block;width:100%;height:auto;aspect-ratio:1600/697;}
/* BILINGUAL INTRO — complete Malayalam text first, then complete English text */
.intro-section{padding:44px 22px 40px;background:transparent;}
.intro-blocks{max-width:680px;margin:0 auto;}
.intro-block{min-width:0;}
.intro-block-en{margin-top:32px;padding-top:32px;border-top:1px solid var(--border);}
.intro-hd{font-family:var(--fd);font-size:clamp(22px,3vw,26px);font-weight:700;color:var(--navy);line-height:1.3;margin:0 0 14px;}
.intro-block p{font-size:16px;line-height:1.75;color:var(--charcoal);margin:0 0 14px;overflow-wrap:anywhere;}
.intro-block p:last-child{margin-bottom:0;}
.intro-block p + .intro-hd{margin-top:28px;}
.intro-block-ml .intro-hd{font-family:var(--fm);font-size:clamp(20px,2.7vw,24px);line-height:1.5;}
.intro-block-ml p{font-family:var(--fm);font-size:16px;line-height:1.9;}
.intro-section .home-actions{margin-top:32px;}
/* SEARCH BOX */
.search-section{background:transparent;padding:48px 22px;}
.search-box{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:32px;max-width:740px;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,.05);}
.search-title{font-family:var(--fd);font-size:22px;font-weight:700;color:var(--navy);margin-bottom:4px;}
.search-title-ml{font-family:var(--fm);font-size:14px;color:var(--gray);display:block;margin-bottom:22px;}
.search-tabs{display:flex;flex-wrap:wrap;gap:4px;background:var(--light);padding:4px;border-radius:8px;margin-bottom:20px;}
.search-tab{flex:1;padding:8px;border:none;background:transparent;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;font-family:var(--fb);color:var(--gray);transition:all .2s;line-height:1.35;}
.search-tab.active{background:white;color:var(--navy);box-shadow:0 1px 4px rgba(0,0,0,.1);}
.search-input-wrap{position:relative;margin-bottom:16px;}
.search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gray);}
.search-input{width:100%;padding:13px 16px 13px 42px;border:1.5px solid var(--border);border-radius:8px;font-size:16px;font-family:var(--fb);color:var(--charcoal);outline:none;transition:border-color .2s;min-height:48px;}
.search-input:focus{border-color:var(--navy);}
.search-input::placeholder{color:#aaa;}
.cascade-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}
.cascade-select{padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:16px;font-family:var(--fb);color:var(--charcoal);background:white;cursor:pointer;outline:none;transition:border-color .2s;min-height:48px;}
.cascade-select:focus{border-color:var(--navy);}
.pincode-row{display:flex;gap:10px;margin-bottom:16px;}
.pincode-input{flex:1;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:16px;font-family:var(--fb);color:var(--charcoal);outline:none;transition:border-color .2s;min-height:48px;}
.pincode-input:focus{border-color:var(--navy);}
.search-btn{width:100%;background:var(--navy);color:white;border:none;padding:14px 16px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:background .2s;min-height:48px;line-height:1.35;}
.search-btn:hover{background:var(--saffron);}
.search-note{font-size:11px;color:var(--gray);text-align:center;margin-top:12px;font-style:italic;line-height:1.5;}
/* HOW IT WORKS */
.hiw{padding:60px 22px;background:transparent;}
.section-hd{text-align:center;margin-bottom:40px;}
.section-eyebrow{font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--saffron);font-weight:600;margin-bottom:10px;display:block;}
.section-title{font-family:var(--fd);font-size:clamp(28px,5vw,44px);font-weight:700;color:var(--navy);line-height:1.2;letter-spacing:-.01em;}
.section-title-ml{font-family:var(--fm);font-size:clamp(16px,3vw,24px);color:var(--navy);opacity:.6;display:block;margin-top:4px;}
.section-sub{font-size:14px;color:var(--gray);max-width:460px;margin:14px auto 0;line-height:1.7;}
.steps{display:flex;flex-direction:column;gap:0;}
.step{display:flex;gap:18px;padding:20px 0;border-bottom:1px solid var(--border);min-width:0;}
.step:last-child{border-bottom:none;}
.step-num{width:44px;height:44px;border-radius:50%;background:var(--navy);color:white;display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:18px;font-weight:700;flex-shrink:0;}
.step-body{min-width:0;flex:1;}
.step-title{font-size:15px;font-weight:600;color:var(--navy);margin-bottom:3px;}
.step-title-ml{font-family:var(--fm);font-size:12px;color:var(--gray);display:block;margin-bottom:5px;}
.step-text{font-size:13px;color:var(--gray);line-height:1.65;overflow-wrap:anywhere;}
/* TIERS */
.tiers{padding:72px 22px;background:transparent;}
.tiers-grid{display:flex;flex-direction:column;gap:16px;max-width:1000px;margin:0 auto;}
.tier-card{border:1.5px solid var(--border);border-radius:10px;padding:28px 24px;position:relative;transition:all .3s;background:white;}
.tier-card:hover{border-color:var(--navy);box-shadow:0 4px 20px rgba(15,31,61,.08);}
.tier-card.distinguished{border-color:var(--gold);background:linear-gradient(135deg,var(--goldbg) 0%,white 50%);}
.tier-card.accomplished{border-color:var(--navy);background:linear-gradient(135deg,#F0F2FF 0%,white 50%);}
.tier-card.emerging{background:white;}
.tier-pop{position:absolute;top:-11px;left:24px;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 12px;border-radius:10px;white-space:nowrap;}
.tier-pop.d{background:var(--gold);color:white;}
.tier-pop.a{background:var(--navy);color:white;}
.tier-pop.e{background:var(--green);color:white;}
.tier-name-en{font-family:var(--fd);font-size:26px;font-weight:700;color:var(--navy);margin-bottom:2px;}
.tier-name-ml{font-family:var(--fm);font-size:16px;color:var(--gray);display:block;margin-bottom:10px;}
.tier-who{font-size:12px;color:var(--gray);margin-bottom:16px;line-height:1.6;}
.tier-who-ml{font-family:var(--fm);font-size:11px;color:var(--gray);display:block;margin-top:3px;line-height:1.6;}
.tier-price{font-family:var(--fd);font-size:32px;font-weight:700;color:var(--saffron);line-height:1;}
.tier-price-row{margin:2px 0 12px;}
.tier-price-sub{font-size:12px;color:var(--gray);margin-top:2px;}
.tier-gst{font-size:11px;color:var(--gray);margin-bottom:16px;margin-top:3px;}
.tier-hr{height:1px;background:var(--border);margin-bottom:16px;}
.tier-features{display:flex;flex-direction:column;gap:7px;margin-bottom:20px;}
.tier-f{display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--gray);line-height:1.5;}
.tier-f-check{color:var(--green);font-size:13px;flex-shrink:0;margin-top:1px;}
.tier-f-x{color:#D1D5DB;font-size:13px;flex-shrink:0;margin-top:1px;}
.tier-btn{width:100%;padding:11px;border-radius:6px;font-size:13px;font-weight:600;font-family:var(--fb);cursor:pointer;transition:all .2s;}
.tbtn-d{background:var(--gold);color:white;border:none;}
.tbtn-d:hover{background:#8a6d3b;}
.tbtn-a{background:var(--navy);color:white;border:none;}
.tbtn-a:hover{background:var(--saffron);}
.tbtn-e{background:transparent;color:var(--navy);border:1.5px solid var(--navy);}
.tbtn-e:hover{background:var(--navy);color:white;}
/* URL SECTION */
.url-section{padding:56px 22px;background:transparent;border-top:1px solid var(--border);}
.url-box{background:white;border:1.5px solid var(--border);border-radius:12px;padding:32px;max-width:680px;margin:0 auto;}
.url-title{font-family:var(--fd);font-size:22px;font-weight:700;color:var(--navy);margin-bottom:4px;}
.url-title-ml{font-family:var(--fm);font-size:14px;color:var(--gray);display:block;margin-bottom:20px;}
.url-examples{display:flex;flex-direction:column;gap:10px;margin-bottom:20px;}
.url-example{background:var(--light);border-radius:6px;padding:12px 16px;font-family:monospace;font-size:13px;color:var(--navy);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;overflow-wrap:anywhere;word-break:break-word;}
.url-badge{font-size:10px;font-family:var(--fb);background:var(--navy);color:white;padding:2px 8px;border-radius:10px;letter-spacing:.5px;flex-shrink:0;}
.url-note{font-size:13px;color:var(--gray);line-height:1.7;margin-bottom:16px;overflow-wrap:anywhere;}
.url-note-ml{font-family:var(--fm);font-size:12px;color:var(--gray);display:block;margin-top:4px;line-height:1.7;}
.url-custom{background:var(--greenbg);border:1px solid #cfe0cb;border-radius:8px;padding:14px 16px;margin-top:12px;}
.url-custom-title{font-size:13px;font-weight:600;color:var(--green);margin-bottom:4px;}
.url-custom-text{font-size:12px;color:var(--gray);line-height:1.6;overflow-wrap:anywhere;}
.url-custom-text-ml{font-family:var(--fm);font-size:11px;color:var(--gray);display:block;margin-top:3px;line-height:1.6;}
/* WELLWISHER */
.ww-section{padding:60px 22px;background:transparent;border-top:1px solid var(--border);}
.ww-inner{max-width:680px;margin:0 auto;text-align:center;}
.ww-icon{font-size:40px;margin-bottom:16px;display:block;}
.ww-title{font-family:var(--fd);font-size:26px;font-weight:700;color:var(--navy);margin-bottom:4px;}
.ww-title-ml{font-family:var(--fm);font-size:16px;color:var(--gray);display:block;margin-bottom:14px;}
.ww-text{font-size:14px;color:var(--gray);line-height:1.75;margin-bottom:6px;}
.ww-text-ml{font-family:var(--fm);font-size:12px;color:var(--gray);line-height:1.75;display:block;margin-bottom:24px;}
.ww-btn{background:transparent;border:1.5px solid var(--saffron);color:var(--saffron);padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:all .2s;}
.ww-btn:hover{background:var(--saffron);color:white;}
/* Recommend Someone — quiet secondary action below the introduction */
.recommend-strip{padding:32px 22px;text-align:center;background:var(--off);}
.recommend-strip a{color:var(--navy);text-decoration:none;}
.recommend-strip a:hover{color:var(--saffron);}
.recommend-strip strong{display:inline-block;font-size:15px;font-weight:600;border-bottom:1px dotted var(--navy);padding-bottom:2px;}
.recommend-strip a:hover strong{border-color:var(--saffron);}
.recommend-strip span{display:block;font-size:12.5px;color:var(--gray);margin-top:6px;}
.mem-section{padding:56px 22px;background:var(--light);border-top:1px solid var(--border);border-bottom:1px solid var(--border);text-align:center;}
.mem-title{font-family:var(--fd);font-size:26px;font-weight:600;color:var(--navy);margin-bottom:4px;}
.mem-title-ml{font-family:var(--fm);font-size:16px;color:var(--gray);display:block;margin-bottom:14px;}
.mem-text{font-size:14px;color:#46554d;line-height:1.75;max-width:560px;margin:0 auto 6px;overflow-wrap:anywhere;}
.mem-text-ml{font-family:var(--fm);font-size:12px;color:var(--gray);line-height:1.75;display:block;max-width:560px;margin:0 auto 24px;overflow-wrap:anywhere;}
.mem-btn{background:var(--navy);color:white;border:none;padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:all .2s;text-decoration:none;display:inline-block;min-height:44px;line-height:1.4;}
.mem-btn:hover{background:#9d5e22;}
/* FOOTER */
/* TRANSLATE DROPDOWN */
.translate-wrap{position:relative;}
.translate-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:white;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.1);min-width:160px;z-index:200;}
.translate-dropdown.open{display:block;}
.translate-dropdown a{display:block;padding:12px 16px;font-size:13px;color:var(--charcoal);text-decoration:none;transition:background .15s;min-height:44px;}
.translate-dropdown a:hover{background:var(--light);}
.translate-dropdown a:first-child{border-radius:8px 8px 0 0;}
.translate-dropdown a:last-child{border-radius:0 0 8px 8px;}
/* MOBILE */
@media(max-width:899px){
  .container{padding:0 16px;}
  .nav-inner{height:auto;min-height:56px;padding:8px 14px;flex-wrap:wrap;align-items:center;}
  .nav-logo{order:1;}
  .nav-logo img{height:46px;}
  .nav-menu-btn{display:inline-flex;order:2;margin-left:auto;}
  .nav-right{order:3;width:100%;justify-content:flex-end;gap:8px;padding-top:2px;}
  .nav-links{order:4;width:100%;flex-direction:column;gap:0;padding:6px 0 4px;border-top:1px solid var(--border);margin-top:4px;}
  nav.nav-open .nav-links{display:flex;}
  .nav-links a{display:flex;align-items:center;min-height:44px;padding:10px 4px;font-size:14px;color:var(--navy);}
  .translate-label{display:none;}
  .translate-btn{padding:8px 10px;min-width:44px;justify-content:center;}
  /* Translated labels can push the button to the row's left edge; open rightwards so the menu stays on screen. */
  .translate-dropdown{right:auto;left:0;}
  .nav-login,.nav-cta{padding:8px 12px;font-size:12px;}
  .hero{padding:36px 16px 36px;}
  .hero-main-heading{margin-bottom:0;}
  .hero-banner{margin-top:22px;}
  .intro-section{padding:32px 16px 32px;}
  .intro-block-en{margin-top:28px;padding-top:28px;}
  .hero-main-heading h1{font-size:clamp(26px,7.2vw,38px);line-height:1.2;}
  .hero-main-heading .hero-ml{font-size:clamp(16px,4.6vw,22px);line-height:1.4;margin-top:10px;}
  .search-section{padding:36px 16px;}
  .search-box{padding:20px 16px;}
  .search-tabs{flex-direction:column;gap:6px;padding:6px;}
  .search-tab{flex:none;width:100%;text-align:left;padding:12px 14px;font-size:13px;min-height:48px;}
  .home-actions{flex-direction:column;align-items:stretch;gap:12px;margin-top:20px;max-width:740px;margin-left:auto;margin-right:auto;}
  .home-action{display:flex;align-items:center;justify-content:center;min-height:48px;padding:14px 16px;border:1.5px solid var(--navy);border-radius:8px;border-bottom:1.5px solid var(--navy);font-size:15px;background:white;}
  .home-action:hover{color:white;background:var(--navy);border-color:var(--navy);}
  .hiw{padding:44px 16px;}
  .section-hd{margin-bottom:32px;}
  .step{gap:14px;padding:18px 0;}
  .step-title{font-size:16px;}
  .step-title-ml{font-size:13px;}
  .step-text{font-size:14px;line-height:1.7;}
  .ep-section{padding:36px 16px 12px;}
  .url-section{padding:40px 16px;}
  .url-box{padding:22px 16px;}
  .url-example{font-size:12px;padding:12px 14px;}
  .mem-section{padding:44px 16px;}
  .mem-title{font-size:clamp(22px,6vw,26px);}
  .mem-text,.mem-text-ml{font-size:14px;}
  .mem-text-ml{font-size:13px;}
  footer{padding:40px 16px 24px;}
  .f-more-note{max-width:100%;}
}
@media(max-width:340px){
  .nav-login{display:none;}
  nav.nav-open .nav-links .nav-login-mobile{display:flex;}
  .hero-main-heading h1{font-size:clamp(24px,7vw,30px);}
}
/* RESPONSIVE */
@media(min-width:600px){
  .cascade-row{grid-template-columns:repeat(3,1fr);}
  .tiers-grid{flex-direction:row;align-items:stretch;}
  .tier-card{flex:1;}
  .f-bottom{flex-direction:row;justify-content:space-between;text-align:left;}
  .search-input,.cascade-select,.pincode-input{font-size:14px;}
}
@media(min-width:900px){
  .nav-menu-btn{display:none;}
  .nav-links{display:flex;}
  .nav-links .nav-login-mobile{display:none !important;}
  .steps{flex-direction:row;gap:20px;}
  .step{flex-direction:column;border-bottom:none;border-right:1px solid var(--border);padding:0 20px 0 0;flex:1;}
  .step:last-child{border-right:none;padding-right:0;}
  .step-num{margin-bottom:14px;}
}
</style>
</head>
<body>
<!-- GOOGLE TRANSLATE (hidden, triggered by button) -->
@include('partials.google-translate')
<script>
function toggleNavMenu(){
  var nav=document.getElementById('site-nav');
  var btn=document.getElementById('nav-menu-btn');
  var open=nav.classList.toggle('nav-open');
  if(btn){
    btn.setAttribute('aria-expanded',open?'true':'false');
    btn.setAttribute('aria-label',open?'Close menu':'Open menu');
  }
}
function closeNavMenu(){
  var nav=document.getElementById('site-nav');
  var btn=document.getElementById('nav-menu-btn');
  if(nav){nav.classList.remove('nav-open');}
  if(btn){
    btn.setAttribute('aria-expanded','false');
    btn.setAttribute('aria-label','Open menu');
  }
}
document.addEventListener('click',function(e){
  if(!e.target.closest('#site-nav'))
    closeNavMenu();
});
</script>
<!-- NAV -->
<nav id="site-nav">
  <div class="nav-inner">
    <a class="nav-logo" href="{{ route('home') }}">
      <img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks">
    </a>
    <button type="button" class="nav-menu-btn" id="nav-menu-btn" aria-expanded="false" aria-controls="nav-links" aria-label="Open menu" onclick="toggleNavMenu()">☰</button>
    <ul class="nav-links" id="nav-links">
      <li><a href="#search" onclick="closeNavMenu()">Search</a></li>
      <li><a href="#hiw" onclick="closeNavMenu()">How It Works</a></li>
      <li><a href="{{ route('faq-charges') }}">FAQ &amp; Charges</a></li>
      <li><a href="{{ route('gallery.index') }}">Gallery</a></li>
      <li><a href="{{ route('demo-profiles.index') }}">View Demo Profiles</a></li>
      <li><a class="nav-login-mobile" href="{{ route('login') }}">Sign In</a></li>
    </ul>
    <div class="nav-right">
      @include('partials.translate-control')
      <a class="nav-login" href="{{ route('login') }}">Sign In</a>
      <a class="nav-cta" href="{{ route('apply') }}">Create Profile</a>
    </div>
  </div>
</nav>
<!-- HERO -->
<section class="hero">
  <div class="hero-main-heading">
    <h1 class="hero-ml-primary" lang="ml">വിവിധ മേഖലകളിലെ ജനനായകരുടെ ഡിജിറ്റൽ ഗാലറി.</h1>
    <span class="hero-en-secondary">A digital gallery of leaders among the people.</span>
  </div>
  <div class="hero-banner">
    <img src="{{ asset('images/jannayaks-hero.jpeg') }}" width="1600" height="697" alt="നിങ്ങളുടെ നേട്ടങ്ങൾ, ലോകമറിയട്ടെ. Celebrate your work, herald it. Men and women from Kerala public life against a Kerala landscape with palm trees and traditional buildings." fetchpriority="high">
  </div>
</section>
<!-- INTRO -->
<section class="intro-section">
  <div class="intro-blocks">
    <div class="intro-block intro-block-ml" lang="ml">
      <h2 class="intro-hd">എന്താണ് Jannayaks?</h2>
      <p>വ്യക്തികളുടെ സംഭാവനകളും നേട്ടങ്ങളും രേഖപ്പെടുത്തുന്ന ഒരു ഡിജിറ്റൽ ഗാലറിയാണ് Jannayaks. ഒരു മാഗസിനിൽ പ്രസിദ്ധീകരിക്കുന്ന വ്യക്തിവിവരണങ്ങളോട് സാമ്യമുള്ളതാണ് ഇതിന്റെ ഉള്ളടക്കം.</p>
      <p>സ്വന്തം നേട്ടങ്ങൾ പൊതുവേദികളിൽ അവതരിപ്പിക്കാൻ അധികം അവസരങ്ങൾ ലഭിക്കാത്ത മേഖലകളിൽ പ്രവർത്തിക്കുന്നവരുടെ സംഭാവനകൾക്ക് അർഹമായ ഇടം നൽകുകയാണ് ഞങ്ങളുടെ ലക്ഷ്യം. നിശ്ശബ്ദമായി നേടിയെടുത്ത നേട്ടങ്ങൾ പോലും സോഷ്യൽ മീഡിയയിലെ ഒരു പോസ്റ്റിൽ ഒതുങ്ങി മാഞ്ഞുപോകേണ്ടതില്ല. അവ കൂടുതൽ കാലം നിലനിൽക്കുന്ന ഒരു ഡിജിറ്റൽ ഗാലറിയിൽ രേഖപ്പെടുത്തൂ.</p>
      <h2 class="intro-hd">എങ്ങനെയാണ് ഇത് സാധ്യമാകുന്നത്?</h2>
      <p>ഒരു മാഗസിനിൽ വ്യക്തിവിവരണം തയ്യാറാക്കുന്നതുപോലെ, ഒരു ഓൺലൈൻ അഭിമുഖത്തിലൂടെയാണ് നിങ്ങളുടെ വ്യക്തിവിവരണത്തിന് ഞങ്ങൾ രൂപം നൽകുന്നത്.</p>
      <p>നിങ്ങളുടെ ജീവിതയാത്രയും നേട്ടങ്ങളും സംഭാവനകളും ഉൾക്കൊള്ളുന്ന ഒരു വ്യക്തിവിവരണം എ.ഐ.യുടെ സഹായത്തോടെ തയ്യാറാക്കുന്നു. ഒരു ബയോഡാറ്റയിൽ ഒതുങ്ങാത്ത നിങ്ങളുടെ അനുഭവങ്ങളും പ്രവർത്തനങ്ങളുമെല്ലാം അതിൽ ഉൾപ്പെടുത്താൻ ശ്രമിക്കുന്നു. ഞങ്ങളുടെ എഡിറ്റോറിയൽ ടീം ഇത് പരിശോധിക്കുകയും നിങ്ങളുടെ അംഗീകാരം നേടുകയും ചെയ്തശേഷമാണ് പ്രസിദ്ധീകരിക്കുന്നത്.</p>
      <p>"jannayaks.in/your.name" എന്നതുപോലുള്ള ഒരു പ്രത്യേക ലിങ്ക് സഹിതമാണ് നിങ്ങളുടെ പ്രൊഫൈൽ പ്രസിദ്ധീകരിക്കുന്നത്. ഈ ലിങ്ക് വാട്സ്ആപ്പിലൂടെയും സോഷ്യൽ മീഡിയയിലൂടെയും പങ്കുവയ്ക്കാം. നിങ്ങളുടെ വിസിറ്റിംഗ് കാർഡിലും ഉൾപ്പെടുത്താം.</p>
      <p>അച്ചടിച്ച ഒരു പേജിൽനിന്ന് വ്യത്യസ്തമായി, നിശ്ചിത വ്യവസ്ഥകൾക്കനുസരിച്ച് Payment പുതുക്കിക്കൊണ്ട് നിങ്ങളുടെ പ്രൊഫൈൽ ഓൺലൈനിൽ നിലനിർത്താം. നിങ്ങളുടെ പ്രവർത്തനങ്ങളിലും നേട്ടങ്ങളിലും പുതിയ അധ്യായങ്ങൾ കൂട്ടിച്ചേർക്കുമ്പോൾ പ്രൊഫൈൽ പുതുക്കുകയും ചെയ്യാം.</p>
      <p>ഓരോ പ്രൊഫൈലും പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് വ്യക്തിപരമായി പരിശോധിക്കുന്നു. AI യുടെ സഹായത്തോടെ ആർക്കും ഒരു ജീവചരിത്രം തയ്യാറാക്കാം. എന്നാൽ Jannayaks അതിൽനിന്ന് വ്യത്യസ്തമാണ് — സ്ഥിരമായ എഡിറ്റോറിയൽ നിലവാരം, സൂക്ഷ്മമായ പരിശോധന, നിങ്ങളുടെ അംഗീകാരത്തോടെയുള്ള പ്രസിദ്ധീകരണം, കാലത്തിനനുസരിച്ച് പുതുക്കാവുന്ന ഒരു ഡിജിറ്റൽ രേഖ.</p>
    </div>
    <div class="intro-block intro-block-en" lang="en">
      <h2 class="intro-hd">What is Jannayaks?</h2>
      <p>Jannayaks is a digital gallery that documents the contributions and achievements of individuals. Its content is similar to the personal profiles published in a magazine.</p>
      <p>Our aim is to give due recognition to the contributions of people working in fields where opportunities to present their achievements publicly may be limited. Even achievements quietly accomplished should not simply fade away as another social media post. They deserve to be documented in a digital gallery where they can remain accessible for much longer.</p>
      <h2 class="intro-hd">How does it work?</h2>
      <p>Much like a personal profile prepared for a magazine, we develop your profile through an online interview.</p>
      <p>With the assistance of AI, we prepare a profile covering your life journey, achievements and contributions. We seek to include your experiences and work that a conventional résumé may not fully convey. Our editorial team reviews the profile and obtains your approval before publication.</p>
      <p>Your profile is published with a dedicated link, such as "jannayaks.in/your.name". You can share this link through WhatsApp and social media, or include it on your visiting card.</p>
      <p>Unlike a printed page, your profile can remain online through payment renewals, subject to the applicable terms. You can also update it as new chapters unfold in your work, achievements and contributions.</p>
      <p>Every profile is personally reviewed before publication. Anyone can use AI to prepare a biography. Jannayaks offers something different: consistent editorial standards, careful review, publication with your approval, and a digital record that can be updated over time.</p>
    </div>
  </div>
  <div class="home-actions">
    <a class="home-action" href="{{ route('faq-charges') }}">Tariff Plans →</a>
    <a class="home-action" href="{{ route('demo-profiles.index') }}">Demo Profiles →</a>
  </div>
</section>
<!-- RECOMMEND SOMEONE -->
<section class="recommend-strip">
  <a href="{{ route('recommend.show') }}">
    <strong>Recommend Someone You May Know</strong>
    <span>Know someone whose life or contribution deserves to be recorded?</span>
  </a>
</section>
<!-- HOW IT WORKS -->
<section class="hiw" id="hiw">
  <div class="container">
    <div class="section-hd">
      <span class="section-eyebrow">How It Works / എങ്ങനെ പ്രവർത്തിക്കുന്നു</span>
      <h2 class="section-title">From conversation to live profile
        <span class="section-title-ml">സംഭാഷണത്തിൽ നിന്ന് തത്സമയ പ്രൊഫൈലിലേക്ക്</span>
      </h2>
      <p class="section-sub">From your answers to a live profile, reviewed by a real editor at every step.</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="step-num">01</div>
        <div class="step-body">
          <div class="step-title">Register & Verify
            <span class="step-title-ml">രജിസ്റ്റർ ചെയ്ത് സ്ഥിരീകരിക്കുക</span>
          </div>
          <div class="step-text">Sign in with Google or WhatsApp. Choose your handle, tier, and location. Identity verification runs alongside editorial review before anything publishes.</div>
        </div>
      </div>
      <div class="step">
        <div class="step-num">02</div>
        <div class="step-body">
          <div class="step-title">Answer Our Questions
            <span class="step-title-ml">ചോദ്യങ്ങൾക്ക് ഉത്തരം നൽകുക</span>
          </div>
          <div class="step-text">Fill in our online interview — in Malayalam, English, or both. Answer at your own pace, or at a nearby service centre if typing isn't convenient.</div>
        </div>
      </div>
      <div class="step">
        <div class="step-num">03</div>
        <div class="step-body">
          <div class="step-title">Pay & Generate
            <span class="step-title-ml">പേയ്‌മെന്റ് ചെയ്ത് ജനറേറ്റ് ചെയ്യുക</span>
          </div>
          <div class="step-text">Pay via UPI or card. Our editorial team drafts your biography in English, then in Malayalam — reviewed by a human at every stage, not machine-published.</div>
        </div>
      </div>
      <div class="step">
        <div class="step-num">04</div>
        <div class="step-body">
          <div class="step-title">Go Live
            <span class="step-title-ml">തത്സമയമാക്കുക</span>
          </div>
          <div class="step-text">Approve your profile. jannayaks.in/yourname goes live in both languages — side by side on desktop, stacked on mobile. Public visibility follows an active membership.</div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- SEARCH -->
<section class="search-section" id="search">
  <div class="search-box">
    <div class="search-title">Search</div>
    <span class="search-title-ml">കണ്ടെത്തൂ</span>
    <div class="search-tabs">
      <button class="search-tab active" onclick="switchTab(this,'name')">🔍 By Name / പേര്</button>
      <button class="search-tab" onclick="switchTab(this,'area')">📍 By Area / പ്രദേശം</button>
      <button class="search-tab" onclick="switchTab(this,'pin')">📮 By Pin Code / പിൻകോഡ്</button>
      <button class="search-tab" onclick="switchTab(this,'text')">🔎 Text Search</button>
    </div>
    <div id="tab-name">
      <form method="get" action="{{ route('search.index') }}" id="home-search-form">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input class="search-input" type="search" name="q" id="home-q" placeholder="Type name, ward, panchayat... / പേര്, വാർഡ്, പഞ്ചായത്ത്..." maxlength="200">
        </div>
      </form>
    </div>
    <div id="tab-area" style="display:none;">
      <div class="cascade-row">
        <select class="cascade-select"><option>{{ config('jannayaks.geography.current_state_name') }} ▾</option></select>
        <select class="cascade-select"><option>Select District ▾</option><option>Thiruvananthapuram</option><option>Kollam</option><option>Pathanamthitta</option><option>Alappuzha</option><option>Kottayam</option><option>Idukki</option><option>Ernakulam</option><option>Thrissur</option><option>Palakkad</option><option>Malappuram</option><option>Kozhikode</option><option>Wayanad</option><option>Kannur</option><option>Kasaragod</option></select>
        <select class="cascade-select"><option>Local Body ▾</option></select>
      </div>
      <select class="cascade-select" style="width:100%;margin-bottom:16px;"><option>Select Ward ▾</option></select>
    </div>
    <div id="tab-pin" style="display:none;">
      <div class="pincode-row">
        <input class="pincode-input" type="text" placeholder="Enter 6-digit pin code..." maxlength="6">
      </div>
    </div>
    <div id="tab-text" style="display:none;">
      <div class="search-input-wrap">
        <span class="search-icon">🔎</span>
        <input class="search-input" type="search" name="q" id="home-text-q" placeholder="Search by name, profession, organisation or place…" maxlength="200" form="home-search-form" disabled>
      </div>
    </div>
    <button type="submit" form="home-search-form" class="search-btn">Search — ലോകത്തെവിടെ നിന്നും തിരയൂ 🌍</button>
    <p class="search-note">Search is open worldwide. / ലോകത്തെവിടെ നിന്നും തിരയാവുന്നതാണ്.</p>
  </div>
</section>
<!-- URL NAMING -->
<section class="url-section">
  <div class="url-box">
    <div class="url-title">Your Personal Profile URL</div>
    <span class="url-title-ml">നിങ്ങളുടെ വ്യക്തിഗത പ്രൊഫൈൽ വിലാസം</span>
    <div class="url-note">
      Every member gets a dedicated personal URL at jannayaks.in. You can choose how your name appears — we make it unique. The URL remains stable; public hosting follows your active membership.
      <span class="url-note-ml">ഓരോ അംഗത്തിനും jannayaks.in-ൽ ഒരു സമർപ്പിത വ്യക്തിഗത URL ലഭിക്കുന്നു. നിങ്ങളുടെ പേര് എങ്ങനെ പ്രത്യക്ഷപ്പെടണമെന്ന് നിങ്ങൾക്ക് തിരഞ്ഞെടുക്കാം — ഞങ്ങൾ അത് അദ്വിതീയമാക്കും. URL സ്ഥിരമായിരിക്കും; പൊതു ഹോസ്റ്റിംഗ് സജീവ അംഗത്വത്തെ ആശ്രയിച്ചിരിക്കും.</span>
    </div>
    <div class="url-examples">
      <div class="url-example">
        jannayaks.in/<strong>Abdulrasheed</strong>
        <span class="url-badge">Simple</span>
      </div>
      <div class="url-example">
        jannayaks.in/<strong>sukumar.aluva</strong>
        <span class="url-badge">Name + Place</span>
      </div>
      <div class="url-example">
        jannayaks.in/<strong>jacob.valiyaveed</strong>
        <span class="url-badge">Name + Family Name</span>
      </div>
    </div>
    <div class="url-custom">
      <div class="url-custom-title">✦ Create Your Own Handle</div>
      <div class="url-custom-text">
        You can also choose a completely custom handle — like <strong>johndoe12</strong> or <strong>jj_nemom</strong>. As long as it is available and does not already exist on the platform, it is yours.
        <span class="url-custom-text-ml">നിങ്ങൾ ആഗ്രഹിക്കുന്ന ഒരു കസ്റ്റം ഹാൻഡിൽ തിരഞ്ഞെടുക്കാം — johndoe12 അല്ലെങ്കിൽ jj_nemom പോലുള്ളത്. അത് ലഭ്യമാണെങ്കിൽ, അത് നിങ്ങളുടേതായിരിക്കും.</span>
      </div>
    </div>
  </div>
</section>
<!-- IN MEMORIAM -->
<section class="mem-section" id="memoriam">
  <h2 class="mem-title">In Memoriam</h2>
  <span class="mem-title-ml">ഓർമ്മയ്ക്കായി</span>
  <p class="mem-text">A special section is provided for popular personalities who have passed away. A dignified memorial page for someone who has passed — open to anyone, prepared with the same editorial care as a living profile. For details, write to founder@jannayaks.in.</p>
  <span class="mem-text-ml">യശശരീരുടെ ഓർമക്കായ്‌ ഒരു സ്ഥിരവും അന്തസ്സുള്ളതുമായ അനുസ്മരണ താൾ — ജീവിച്ചിരിക്കുന്ന പ്രൊഫൈലിന്റെ അതേ എഡിറ്റോറിയൽ ശ്രദ്ധയോടെ തയ്യാറാക്കുന്നത്.</span>
  <div>
    <a class="mem-btn" href="{{ route('in-memoriam.index') }}">Create a Memorial</a>
  </div>
</section>
<!-- FOOTER -->
@include('partials.public-footer')
<script>
function switchTab(btn,tab){
  document.querySelectorAll('.search-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  ['name','area','pin','text'].forEach(t=>{
    document.getElementById('tab-'+t).style.display = t===tab ? 'block' : 'none';
  });
  var nq=document.getElementById('home-q'); if(nq) nq.disabled = (tab!=='name');
  var tq=document.getElementById('home-text-q'); if(tq) tq.disabled = (tab!=='text');
}
</script>
</body>
</html>
