<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
<link rel="icon" href="{{ asset('branding/favicon.ico') }}">
<link rel="preload" as="image" type="image/webp" href="{{ asset('images/jannayaks-hero-2.webp') }}" imagesrcset="{{ asset('images/jannayaks-hero-2-800.webp') }} 800w, {{ asset('images/jannayaks-hero-2.webp') }} 1600w" imagesizes="(max-width: 899px) 100vw, 800px" fetchpriority="high">
<title>Jannayaks™ — A Digital Gallery of Notable People Across All Fields</title>
@php($schemaJson = app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->websiteGraph(
    'A Digital Gallery of Notable People Across All Fields. Jannayaks presents editorially prepared biographical profiles in English and Malayalam.',
    url('/search'),
)))
@include('partials.schema-ld', ['json' => $schemaJson])
@include('partials.seo-meta', [
    'title' => 'Jannayaks™ — A Digital Gallery of Notable People Across All Fields',
    'description' => 'A Digital Gallery of Notable People Across All Fields. Jannayaks presents editorially prepared biographical profiles — കർമ്മപഥങ്ങളിൽ ശ്രദ്ധേയരായ വ്യക്തിത്വങ്ങളുടെ ഡിജിറ്റൽ ഗാലറി.',
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
.nav-login{background:transparent;border:1.5px solid var(--navy);color:var(--navy);padding:7px 16px;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;min-height:40px;}
.nav-login:hover{background:var(--navy);color:white;}
.nav-cta{background:var(--navy);color:white;border:none;padding:8px 18px;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:background .2s;text-decoration:none;display:inline-flex;align-items:center;min-height:40px;}
.nav-cta:hover{background:var(--saffron);}
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
.hero-banner picture{display:block;}
.hero-banner img{display:block;width:100%;height:auto;aspect-ratio:1600/697;object-fit:contain;}
/* BILINGUAL INTRO — complete Malayalam text first, then complete English text */
.intro-section{padding:44px 22px 40px;background:transparent;}
.intro-blocks{max-width:680px;margin:0 auto;}
.intro-block{min-width:0;}
.intro-tabs{display:flex;width:max-content;max-width:100%;gap:4px;background:var(--light);padding:4px;border-radius:8px;margin:0 auto 28px;}
.intro-tab{min-width:120px;min-height:40px;padding:8px 18px;border:none;background:transparent;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;font-family:var(--fb);color:var(--gray);transition:all .2s;line-height:1.35;}
.intro-tab[lang="ml"]{font-family:var(--fm);}
.intro-tab:hover{color:var(--navy);}
.intro-tab[aria-selected="true"]{background:white;color:var(--navy);box-shadow:0 1px 4px rgba(0,0,0,.1);}
.intro-block[hidden]{display:none;}
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
/* HERO CTAS */
.hero-ctas{display:flex;gap:14px;flex-wrap:wrap;justify-content:center;margin:20px 0 26px;}
.hero-cta{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-height:52px;padding:12px 26px;border-radius:8px;font-size:15px;font-weight:600;font-family:var(--fb);text-decoration:none;transition:all .2s;}
.hero-cta .cta-ml{font-family:var(--fm);font-size:12.5px;font-weight:500;opacity:.85;}
.hero-cta-primary{background:var(--navy);color:white;border:1.5px solid var(--navy);}
.hero-cta-primary:hover{background:var(--saffron);border-color:var(--saffron);color:white;}
.hero-cta-secondary{background:white;color:var(--navy);border:1.5px solid var(--navy);}
.hero-cta-secondary:hover{background:var(--navy);color:white;}
/* SAMPLE PROFILES */
.demo-section{padding:56px 22px;background:transparent;border-top:1px solid var(--border);}
.demo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:1000px;margin:26px auto 18px;}
.demo-card{position:relative;display:block;background:white;border:1.5px solid var(--border);border-radius:10px;overflow:hidden;text-decoration:none;transition:all .2s;}
.demo-card:hover{border-color:var(--navy);box-shadow:0 4px 18px rgba(15,31,61,.08);}
.demo-card-photo{aspect-ratio:4/3;background:linear-gradient(145deg,#eaf2e7,#f3f8f0);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
.demo-card-photo img{width:100%;height:100%;object-fit:cover;}
.demo-card-photo .demo-initial{font-family:var(--fd);font-size:44px;color:var(--gray);}
.demo-badge{position:absolute;top:10px;left:10px;z-index:2;background:var(--navy);color:white;font:700 10px/1 var(--fb);letter-spacing:.14em;text-transform:uppercase;padding:5px 10px;border-radius:4px;}
.demo-card-body{padding:14px 16px 16px;}
.demo-card-body h3{font-family:var(--fd);font-size:17px;font-weight:700;color:var(--navy);margin:0 0 2px;}
.demo-card-body p{font-size:12px;color:var(--gray);margin:0;line-height:1.55;}
.demo-more{text-align:center;}
.demo-more a{font-size:13.5px;font-weight:600;color:var(--navy);text-decoration:none;border-bottom:1.5px solid var(--navy);padding-bottom:2px;}
.demo-more a:hover{color:var(--saffron);border-color:var(--saffron);}
/* WHY JANNAYAKS */
.why-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:1000px;margin:26px auto 0;}
.why-card{background:white;border:1.5px solid var(--border);border-radius:10px;padding:24px 22px;text-align:center;}
.why-icon{font-size:30px;display:block;margin-bottom:10px;}
.why-hd{font-family:var(--fd);font-size:17px;font-weight:700;color:var(--navy);margin:0 0 2px;}
.why-hd-ml{font-family:var(--fm);font-size:13px;color:var(--gray);display:block;margin-bottom:8px;}
.why-text{font-size:13px;color:var(--gray);line-height:1.7;margin:0;}
.why-text-ml{font-family:var(--fm);font-size:12px;color:var(--gray);line-height:1.75;display:block;margin-top:4px;}
/* PRICING */
.price-section{padding:56px 22px;border-top:1px solid var(--border);}
.price-card{background:white;border:1.5px solid var(--navy);border-radius:12px;padding:34px 28px;max-width:560px;margin:26px auto 0;text-align:center;}
.price-pop{display:inline-block;font-size:10px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;background:var(--green);color:white;padding:4px 12px;border-radius:10px;margin-bottom:14px;}
.price-amount{font-family:var(--fd);font-size:52px;font-weight:700;color:var(--navy);line-height:1;}
.price-includes{font-size:14px;color:var(--charcoal);margin:10px 0 2px;line-height:1.6;}
.price-includes-ml{font-family:var(--fm);font-size:12.5px;color:var(--gray);display:block;line-height:1.7;}
.price-renewal{font-size:13px;color:var(--gray);margin-top:12px;}
.price-renewal-ml{font-family:var(--fm);font-size:12px;color:var(--gray);display:block;margin-top:2px;}
.price-faq{display:inline-block;margin-top:16px;font-size:13.5px;font-weight:600;color:var(--navy);text-decoration:none;border-bottom:1.5px solid var(--navy);padding-bottom:2px;}
.price-faq:hover{color:var(--saffron);border-color:var(--saffron);}
/* EDITORIAL CARE */
.care-section{padding:56px 22px;border-top:1px solid var(--border);}
.care-box{background:white;border:1.5px solid var(--border);border-radius:12px;padding:30px 28px;max-width:760px;margin:26px auto 0;}
.care-text{font-size:14px;color:var(--charcoal);line-height:1.8;margin:0;}
.care-text-ml{font-family:var(--fm);font-size:13px;color:var(--gray);line-height:1.85;display:block;margin-top:10px;}
/* RECOMMEND */
.rec-section{padding:56px 22px;border-top:1px solid var(--border);}
.rec-box{max-width:680px;margin:26px auto 0;text-align:center;background:var(--light);border:1.5px solid var(--border);border-radius:12px;padding:32px 26px;}
.rec-text{font-size:14px;color:var(--charcoal);line-height:1.7;margin:8px 0 18px;}
.rec-text-ml{font-family:var(--fm);font-size:12.5px;color:var(--gray);line-height:1.75;display:block;margin-top:4px;}
.rec-btn{display:inline-block;background:transparent;border:1.5px solid var(--navy);color:var(--navy);padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;font-family:var(--fb);text-decoration:none;transition:all .2s;}
.rec-btn:hover{background:var(--navy);color:white;}
.rec-btn .cta-ml{font-family:var(--fm);font-size:12px;font-weight:500;display:block;opacity:.85;}
/* FINAL CTA */
.final-section{padding:64px 22px;background:var(--navy);text-align:center;}
.final-hd{font-family:var(--fd);font-size:clamp(24px,5vw,34px);font-weight:700;color:white;margin:0 0 4px;}
.final-hd-ml{font-family:var(--fm);font-size:clamp(15px,3.4vw,20px);color:#dbe6f5;display:block;margin-bottom:14px;}
.final-text{font-size:14.5px;color:#dbe6f5;line-height:1.75;max-width:640px;margin:0 auto 6px;}
.final-text-ml{font-family:var(--fm);font-size:13px;color:#c3d3ea;line-height:1.8;display:block;margin-bottom:24px;}
.final-btn{display:inline-flex;flex-direction:column;align-items:center;gap:2px;background:white;color:var(--navy);padding:13px 30px;border-radius:8px;font-size:15px;font-weight:700;font-family:var(--fb);text-decoration:none;transition:all .2s;}
.final-btn .cta-ml{font-family:var(--fm);font-size:12.5px;font-weight:500;color:var(--gray);}
.final-btn:hover{background:var(--saffron);color:white;}
.final-btn:hover .cta-ml{color:white;}
/* Refinement: hero hierarchy (English dominant), quiet pricing, tighter rhythm */
.hero-main-heading h1.hero-en-primary{font-family:var(--fd);font-size:clamp(30px,4.6vw,52px);line-height:1.15;font-weight:700;color:var(--navy);margin:0 0 16px;letter-spacing:-.01em;overflow-wrap:anywhere;}
.hero-ml-secondary{font-family:var(--fm);font-size:clamp(21px,2.7vw,29px);line-height:1.7;font-weight:500;color:#52758a;margin:0;overflow-wrap:anywhere;}
.hero{padding:48px 22px 40px;}
.hero-main-heading{margin-bottom:26px;}
.hero-ctas{margin:24px 0 30px;}
.demo-section,.price-section,.care-section,.rec-section,.url-section{padding:44px 22px;}
.hiw{padding:44px 22px;}
.search-section{padding:40px 22px;}
.section-hd{margin-bottom:26px;}
.section-title{font-size:clamp(26px,4.2vw,38px);}
.why-grid,.demo-grid{margin-top:18px;}
.why-card{padding:20px 20px;}
.care-box,.rec-box{margin-top:18px;padding:24px 24px;}
.why-remember{max-width:760px;margin:26px auto 0;text-align:center;}
.why-remember-hd{font-family:var(--fd);font-size:clamp(19px,2.4vw,24px);font-weight:600;color:var(--navy);line-height:1.4;margin:0;}
.why-remember-hd-ml{font-family:var(--fm);font-size:16px;color:var(--navy);opacity:.8;line-height:1.75;margin:4px 0 10px;}
.why-remember-text{font-size:14.5px;color:var(--charcoal);line-height:1.75;margin:0;}
.why-remember-text-ml{font-family:var(--fm);font-size:13.5px;color:var(--gray);line-height:1.9;margin:6px 0 0;}
.price-text{max-width:640px;margin:0 auto;text-align:center;}
.price-line{font-size:clamp(19px,2.4vw,23px);color:var(--navy);margin:0 0 10px;line-height:1.4;}
.price-line strong{font-weight:700;}
.price-line-ml{display:block;font-family:var(--fm);font-size:15px;color:var(--navy);opacity:.75;margin-top:2px;}
.price-detail{font-size:14.5px;color:var(--charcoal);line-height:1.7;margin:0 0 8px;}
.price-detail-ml{display:block;font-family:var(--fm);font-size:13.5px;color:var(--gray);line-height:1.85;}
/* HIW horizontal on desktop */
@media(min-width:900px){
  .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;}
  .step{flex-direction:column;border-bottom:none;border-top:2px solid var(--border);padding:20px 4px 0;text-align:left;}
  .step-num{margin-bottom:12px;}
}
@media(max-width:899px){
  .demo-grid{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:14px;padding-bottom:8px;-webkit-overflow-scrolling:touch;}
  .demo-card{min-width:240px;flex:0 0 240px;scroll-snap-align:start;}
  .why-grid{grid-template-columns:1fr;}
  .hero-ctas{flex-direction:column;align-items:stretch;max-width:340px;margin-left:auto;margin-right:auto;}
  .hero-cta{min-height:56px;}
  .hero-main-heading h1.hero-en-primary{font-size:clamp(27px,7vw,34px);margin-bottom:12px;}
  .hero-ml-secondary{font-size:clamp(21px,5.6vw,27px);line-height:1.7;margin-bottom:4px;}
}
/* FOOTER */
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
  .nav-login,.nav-cta{padding:8px 12px;font-size:12px;}
  .hero{padding:36px 16px 36px;}
  .hero-main-heading{margin-bottom:0;}
  .hero-banner{margin-top:22px;}
  .intro-section{padding:32px 16px 32px;}
  .intro-tabs{margin-bottom:22px;}
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
<noscript><style>.intro-tabs{display:none;}.intro-block[hidden]{display:block;margin-top:32px;}</style></noscript>
@include('partials.ga4')
</head>
<body>
{{-- Google Translate control temporarily omitted from the homepage only.
     Shared partials (partials.translate-control / partials.google-translate)
     remain for other public pages and future multilingual work. --}}
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
      <li><a href="{{ route('gallery.index') }}" onclick="closeNavMenu()">Gallery</a></li>
      <li><a href="#hiw" onclick="closeNavMenu()">How It Works</a></li>
      <li><a href="#pricing" onclick="closeNavMenu()">Pricing</a></li>
      <li><a class="nav-login-mobile" href="{{ route('login') }}">Sign In</a></li>
    </ul>
    <div class="nav-right">
      <a class="nav-login" href="{{ route('login') }}">Sign In</a>
      <a class="nav-cta" href="{{ route('apply') }}">Create Profile</a>
    </div>
  </div>
</nav>
<!-- HERO -->
<section class="hero">
  <div class="hero-main-heading">
    <h1 class="hero-en-primary">A Digital Gallery of Notable People Across All Fields.</h1>
    <p class="hero-ml-secondary" lang="ml">കർമ്മപഥങ്ങളിൽ ശ്രദ്ധേയരായ വ്യക്തിത്വങ്ങളുടെ ഡിജിറ്റൽ ഗാലറി.</p>
  </div>
  <div class="hero-ctas">
    <a class="hero-cta hero-cta-primary" href="{{ route('apply') }}">Create Your Profile
      <span class="cta-ml" lang="ml">നിങ്ങളുടെ പ്രൊഫൈൽ സൃഷ്ടിക്കൂ</span>
    </a>
    <a class="hero-cta hero-cta-secondary" href="{{ route('gallery.index') }}">Explore Profiles
      <span class="cta-ml" lang="ml">പ്രൊഫൈലുകൾ കാണുക</span>
    </a>
  </div>
  <div class="hero-banner">
    <picture>
      <source type="image/webp" srcset="{{ asset('images/jannayaks-hero-2-800.webp') }} 800w, {{ asset('images/jannayaks-hero-2.webp') }} 1600w" sizes="(max-width: 899px) 100vw, 800px">
      <img src="{{ asset('images/jannayaks-hero-2-source.jpeg') }}" width="1600" height="697" alt="നിങ്ങളുടെ നേട്ടങ്ങൾ, ലോകമറിയട്ടെ. Celebrate your work, herald it. Men and women from Kerala public life against a Kerala landscape with palm trees and traditional buildings." fetchpriority="high" decoding="async">
    </picture>
  </div>
</section>
<!-- SAMPLE PROFILES -->
<section class="demo-section" id="sample-profiles">
  <div class="section-hd">
    <span class="section-eyebrow">Demo Profiles / മാതൃകാ പ്രൊഫൈലുകൾ</span>
    <h2 class="section-title">See What Your Profile Could Look Like
      <span class="section-title-ml">നിങ്ങളുടെ പ്രൊഫൈൽ എങ്ങനെയായിരിക്കുമെന്ന് കാണൂ</span>
    </h2>
    <p class="section-sub">Explore sample profiles to see how Jannayaks presents individual achievements and life stories.
      <span class="section-title-ml" style="font-family:var(--fm);font-size:13px">വ്യക്തികളുടെ നേട്ടങ്ങളും ജീവിതകഥകളും Jannayaks എങ്ങനെ അവതരിപ്പിക്കുന്നുവെന്ന് അറിയാൻ മാതൃകാ പ്രൊഫൈലുകൾ കാണൂ.</span>
    </p>
  </div>
  <div class="demo-grid">
    @foreach ($demoProfiles as $demoProfile)
      @php($demoPhoto = $presentation->publicProfilePhoto($demoProfile))
      @php($demoUrl = app(\App\Services\ProfileUrlService::class)->publicListingUrl($demoProfile))
      <a class="demo-card" href="{{ $demoUrl }}">
        <div class="demo-card-photo">
          @if($demoPhoto)
            <img src="{{ route('profiles.public.photo', [$demoProfile, $demoPhoto]) }}" alt="{{ $demoPhoto->alt_text ?: ('Portrait of '.$presentation->displayName($demoProfile)) }}" loading="lazy">
          @else
            <span class="demo-initial">{{ mb_strtoupper(mb_substr($presentation->displayName($demoProfile), 0, 1)) }}</span>
          @endif
          <span class="demo-badge">Demo</span>
        </div>
        <div class="demo-card-body">
          <h3>{{ $presentation->displayName($demoProfile) }}</h3>
          <p>{{ $presentation->conciseDescriptor($demoProfile) }}</p>
        </div>
      </a>
    @endforeach
  </div>
  <p class="demo-more"><a href="{{ route('demo-profiles.index') }}">View the complete Demo Profiles collection →</a></p>
</section>
<!-- INTRO -->
<section class="intro-section">
  <div class="intro-blocks">
    <div class="intro-tabs" role="tablist" aria-label="Language / ഭാഷ">
      <button type="button" class="intro-tab" role="tab" id="intro-tab-en" lang="en" aria-selected="true" aria-controls="intro-panel-en" onclick="switchIntroLang('en')">English</button>
      <button type="button" class="intro-tab" role="tab" id="intro-tab-ml" lang="ml" aria-selected="false" aria-controls="intro-panel-ml" tabindex="-1" onclick="switchIntroLang('ml')">മലയാളം</button>
    </div>
    <div class="intro-block intro-block-en" lang="en" id="intro-panel-en" role="tabpanel" aria-labelledby="intro-tab-en">
      <h2 class="intro-hd">What is Jannayaks?</h2>
      <p>Jannayaks is a digital gallery that documents the contributions and achievements of individuals. Its content is similar to the personal profiles published in a magazine.</p>
      <p>Our aim is to give due recognition to the contributions of people working in fields where opportunities to present their achievements publicly may be limited. Even achievements quietly accomplished should not simply fade away as another social media post. They deserve to be documented in a digital gallery where they can remain accessible for much longer.</p>
      <p>Whichever field you are in, have a record of yourself which you can be proud of.</p>
      <p>An English-only profile is also available for those who prefer it.</p>
    </div>
    <div class="intro-block intro-block-ml" lang="ml" id="intro-panel-ml" role="tabpanel" aria-labelledby="intro-tab-ml" hidden>
      <h2 class="intro-hd">എന്താണ് Jannayaks?</h2>
      <p>വ്യക്തികളുടെ സംഭാവനകളും നേട്ടങ്ങളും രേഖപ്പെടുത്തുന്ന ഒരു ഡിജിറ്റൽ ഗാലറിയാണ് Jannayaks. ഒരു മാഗസിനിൽ പ്രസിദ്ധീകരിക്കുന്ന വ്യക്തിവിവരണങ്ങളോട് സാമ്യമുള്ളതാണ് ഇതിന്റെ ഉള്ളടക്കം.</p>
      <p>സ്വന്തം നേട്ടങ്ങൾ പൊതുവേദികളിൽ അവതരിപ്പിക്കാൻ അധികം അവസരങ്ങൾ ലഭിക്കാത്ത മേഖലകളിൽ പ്രവർത്തിക്കുന്നവരുടെ സംഭാവനകൾക്ക് അർഹമായ ഇടം നൽകുകയാണ് ഞങ്ങളുടെ ലക്ഷ്യം. നിശ്ശബ്ദമായി നേടിയെടുത്ത നേട്ടങ്ങൾ പോലും സോഷ്യൽ മീഡിയയിലെ ഒരു പോസ്റ്റിൽ ഒതുങ്ങി മാഞ്ഞുപോകേണ്ടതില്ല. അവ കൂടുതൽ കാലം നിലനിൽക്കുന്ന ഒരു ഡിജിറ്റൽ ഗാലറിയിൽ രേഖപ്പെടുത്തൂ.</p>
      <p>"jannayaks.in/your.name" എന്നതുപോലുള്ള ഒരു പ്രത്യേക ലിങ്ക് സഹിതമാണ് നിങ്ങളുടെ പ്രൊഫൈൽ പ്രസിദ്ധീകരിക്കുന്നത്. ഈ ലിങ്ക് വാട്ട്സ്ആപ്പിലൂടെയും സോഷ്യൽ മീഡിയയിലൂടെയും പങ്കുവയ്ക്കാം. നിങ്ങളുടെ വിസിറ്റിംഗ് കാർഡിലും ഉൾപ്പെടുത്താം.</p>
      <p>അച്ചടിച്ച ഒരു പേജിൽനിന്ന് വ്യത്യസ്തമായി, നിശ്ചിത വ്യവസ്ഥകൾക്കനുസരിച്ച് Payment പുതുക്കിക്കൊണ്ട് നിങ്ങളുടെ പ്രൊഫൈൽ ഓൺലൈനിൽ നിലനിർത്താം. നിങ്ങളുടെ പ്രവർത്തനങ്ങളിലും നേട്ടങ്ങളിലും പുതിയ അധ്യായങ്ങൾ കൂട്ടിച്ചേർക്കുമ്പോൾ പ്രൊഫൈൽ പുതുക്കുകയും ചെയ്യാം.</p>
    </div>
  </div>
  {{-- TEMPORARY launch contact block: remove after the initial period --}}
  <p class="intro-contact" style="margin:18px auto 0;max-width:740px;text-align:center;font-size:15px">
        ചോദ്യങ്ങളുണ്ടോ? Questions?
        <a href="tel:{{ $contactTel }}" style="color:var(--navy);font-weight:600">{{ $contactPhone }}</a> ·
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $contactTel) }}" target="_blank" rel="noopener" style="color:var(--navy);font-weight:600">WhatsApp</a>
  </p>
</section>
<!-- WHY JANNAYAKS -->
<section class="url-section" id="why">
  <div class="section-hd">
    <span class="section-eyebrow">Why Jannayaks? / എന്തുകൊണ്ട് Jannayaks?</span>
    <h2 class="section-title">Why Jannayaks?
      <span class="section-title-ml">എന്തുകൊണ്ട് Jannayaks?</span>
    </h2>
  </div>
  <div class="why-grid">
    <div class="why-card">
      <span class="why-icon" aria-hidden="true">📜</span>
      <h3 class="why-hd">Your Achievements, Documented</h3>
      <span class="why-hd-ml" lang="ml">നിങ്ങളുടെ നേട്ടങ്ങൾ രേഖപ്പെടുത്താം</span>
      <p class="why-text">Present your achievements and contributions through a professionally prepared biographical profile.
        <span class="why-text-ml" lang="ml">നിങ്ങളുടെ നേട്ടങ്ങളും സംഭാവനകളും പ്രൊഫഷണലായി തയ്യാറാക്കിയ ഒരു ജീവചരിത്ര പ്രൊഫൈലിലൂടെ അവതരിപ്പിക്കാം.</span>
      </p>
    </div>
    <div class="why-card">
      <span class="why-icon" aria-hidden="true">🖋️</span>
      <h3 class="why-hd">Professional Editorial Presentation</h3>
      <span class="why-hd-ml" lang="ml">പ്രൊഫഷണൽ Editorial Presentation</span>
      <p class="why-text">Your information is organised and edited into a clear, engaging profile that you can review before publication.
        <span class="why-text-ml" lang="ml">നിങ്ങൾ നൽകുന്ന വിവരങ്ങൾ ക്രമീകരിച്ച് വായിക്കാൻ ആകർഷകമായ പ്രൊഫൈലായി തയ്യാറാക്കുന്നു. പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് അത് പരിശോധിക്കാനും കഴിയും.</span>
      </p>
    </div>
    <div class="why-card">
      <span class="why-icon" aria-hidden="true">🔗</span>
      <h3 class="why-hd">Your Own Profile Link</h3>
      <span class="why-hd-ml" lang="ml">നിങ്ങളുടെ സ്വന്തം Profile Link</span>
      <p class="why-text">Share your Jannayaks profile with friends, family, colleagues and professional contacts through your personal profile URL.
        <span class="why-text-ml" lang="ml">നിങ്ങളുടെ വ്യക്തിഗത Profile URL വഴി സുഹൃത്തുക്കൾക്കും കുടുംബാംഗങ്ങൾക്കും സഹപ്രവർത്തകർക്കും മറ്റുള്ളവർക്കും നിങ്ങളുടെ Jannayaks പ്രൊഫൈൽ പങ്കുവയ്ക്കാം.</span>
      </p>
    </div>
  </div>
  <div class="why-remember">
    <p class="why-remember-hd">Your achievements deserve to be remembered — not just by the world, but by you.</p>
    <p class="why-remember-hd-ml" lang="ml">നിങ്ങളുടെ നേട്ടങ്ങൾ ഓർമ്മിക്കപ്പെടണം — ലോകം മാത്രമല്ല, നിങ്ങളും.</p>
    <p class="why-remember-text">Your Jannayaks profile gives you a place to revisit your journey, remember how far you have come, and take pride in what you have accomplished.</p>
    <p class="why-remember-text-ml" lang="ml">നിങ്ങൾ പിന്നിട്ട വഴികൾ വീണ്ടും കാണാനും, എത്ര ദൂരം മുന്നേറിയെന്ന് ഓർക്കാനും, നേടിയെടുത്തവയിൽ അഭിമാനിക്കാനും നിങ്ങളുടെ Jannayaks പ്രൊഫൈൽ ഒരിടം നൽകുന്നു.</p>
  </div>
</section>
<!-- SEARCH -->
<section class="search-section" id="search">
  <div class="search-box">
    <div class="search-title">Search</div>
    <span class="search-title-ml">കണ്ടെത്തൂ</span>
    <form method="get" action="{{ route('search.index') }}" id="home-search-form">
      <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input class="search-input" type="search" name="q" id="home-q" placeholder="Name, profession, field of work, or place" maxlength="200">
      </div>
      <button type="submit" class="search-btn">Search</button>
    </form>
    <p class="search-note">Search is open worldwide. / ലോകത്തെവിടെ നിന്നും തിരയാവുന്നതാണ്.</p>
  </div>
</section>
<!-- URL NAMING -->
<section class="url-section">
  <div class="url-box">
    <div class="url-title">Your Personal Profile URL</div>
    <span class="url-title-ml">നിങ്ങളുടെ വ്യക്തിഗത പ്രൊഫൈൽ വിലാസം</span>
    <div class="url-note">
      Your preferred name is used to create your personalised Jannayaks profile URL, subject to availability and URL formatting requirements. A short number is added only when that address is already taken. The address stays permanent; public hosting follows an active membership.
      <span class="url-note-ml">നിങ്ങൾ നൽകുന്ന ഇഷ്ടപ്പെട്ട പേര്, ലഭ്യതയ്ക്കും URL ഫോർമാറ്റിനും വിധേയമായി, നിങ്ങളുടെ വ്യക്തിഗത Jannayaks പ്രൊഫൈൽ ലിങ്ക് സൃഷ്ടിക്കാൻ ഉപയോഗിക്കുന്നു. ആ വിലാസം മറ്റൊരാൾക്കുള്ളതാണെങ്കിൽ മാത്രം ഒരു ചെറിയ സംഖ്യ ചേർക്കും. വിലാസം സ്ഥിരമായിരിക്കും; പൊതു ഹോസ്റ്റിംഗ് സജീവ അംഗത്വത്തെ ആശ്രയിച്ചിരിക്കും.</span>
    </div>
    <div class="url-examples">
      <div class="url-example">
        @php($demoSlugExample = $demoProfiles->first()?->slug)
        @if($demoSlugExample !== null && $demoSlugExample !== '')
          <a href="{{ app(\App\Services\ProfileUrlService::class)->publicListingUrl($demoProfiles->first()) }}" style="color:inherit;text-decoration:none">jannayaks.in/<strong>{{ $demoSlugExample }}</strong></a>
        @else
          jannayaks.in/<strong>your.name</strong>
        @endif
        <span class="url-badge">Name</span>
      </div>
      <div class="url-example">
        jannayaks.in/<strong>arun.kumar12</strong>
        <span class="url-badge">Name + number</span>
      </div>
    </div>
    <div class="url-custom">
      <div class="url-custom-title">Choose a name-based address</div>
      <div class="url-custom-text">
        If that address is already taken, a short number can be added, for example <strong>arun.kumar12</strong>. Once your profile is published, its URL cannot be changed.
        <span class="url-custom-text-ml">ആ വിലാസം ലഭ്യമല്ലെങ്കിൽ arun.kumar12 പോലെ ഒരു ചെറിയ സംഖ്യ ചേർക്കാം. പ്രൊഫൈൽ പ്രസിദ്ധീകരിച്ചുകഴിഞ്ഞാൽ അതിന്റെ URL മാറ്റാൻ കഴിയില്ല.</span>
      </div>
    </div>
  </div>
</section>
<!-- HOW IT WORKS -->
<section class="hiw" id="hiw">
  <div class="container">
    <div class="section-hd">
      <span class="section-eyebrow">How It Works / എങ്ങനെ പ്രവർത്തിക്കുന്നു</span>
      <h2 class="section-title">How It Works
        <span class="section-title-ml">എങ്ങനെയാണ് പ്രവർത്തിക്കുന്നത്?</span>
      </h2>
    </div>
    <div class="steps">
      <div class="step">
        <div class="step-num">01</div>
        <div class="step-body">
          <div class="step-title">Apply and Complete the Interview
            <span class="step-title-ml">അപേക്ഷിച്ച് Online Interview പൂർത്തിയാക്കുക</span>
          </div>
          <div class="step-text">Tell us about yourself, your work and your achievements.
            <span class="step-title-ml" lang="ml">നിങ്ങളെക്കുറിച്ചും നിങ്ങളുടെ പ്രവർത്തനങ്ങളെക്കുറിച്ചും നേട്ടങ്ങളെക്കുറിച്ചും വിവരങ്ങൾ നൽകുക.</span>
          </div>
        </div>
      </div>
      <div class="step">
        <div class="step-num">02</div>
        <div class="step-body">
          <div class="step-title">Editorial Preparation
          </div>
          <div class="step-text">We organise and edit your information into a professionally presented profile.
            <span class="step-title-ml" lang="ml">നിങ്ങൾ നൽകിയ വിവരങ്ങൾ ക്രമീകരിച്ച് പ്രൊഫഷണലായി പ്രൊഫൈൽ തയ്യാറാക്കുന്നു.</span>
          </div>
        </div>
      </div>
      <div class="step">
        <div class="step-num">03</div>
        <div class="step-body">
          <div class="step-title">Your Review and Approval
            <span class="step-title-ml">നിങ്ങളുടെ പരിശോധനയും അംഗീകാരവും</span>
          </div>
          <div class="step-text">Review the prepared profile and approve it before publication.
            <span class="step-title-ml" lang="ml">തയ്യാറാക്കിയ പ്രൊഫൈൽ പരിശോധിച്ച് പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് അംഗീകരിക്കുക.</span>
          </div>
        </div>
      </div>
      <div class="step">
        <div class="step-num">04</div>
        <div class="step-body">
          <div class="step-title">Publication
            <span class="step-title-ml">പ്രസിദ്ധീകരണം</span>
          </div>
          <div class="step-text">Your approved profile is published on Jannayaks with its own shareable link.
            <span class="step-title-ml" lang="ml">നിങ്ങൾ അംഗീകരിച്ച പ്രൊഫൈൽ സ്വന്തമായി പങ്കുവയ്ക്കാവുന്ന ലിങ്കോടെ Jannayaks-ൽ പ്രസിദ്ധീകരിക്കുന്നു.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- PRICING -->
<section class="price-section" id="pricing">
  <div class="section-hd">
    <span class="section-eyebrow">Pricing / നിരക്ക്</span>
    <h2 class="section-title">Pricing
      <span class="section-title-ml">നിരക്ക്</span>
    </h2>
  </div>
  <div class="price-text">
    <p class="price-line"><strong>Jannayaks Profile: ₹4,800 + applicable GST</strong>
      <span class="price-line-ml" lang="ml">Jannayaks Profile: ₹4,800 + ബാധകമായ GST</span>
    </p>
    <p class="price-detail">Includes profile preparation, editorial presentation, publication and first-year hosting.
      <span class="price-detail-ml" lang="ml">പ്രൊഫൈൽ തയ്യാറാക്കൽ, Editorial Presentation, പ്രസിദ്ധീകരണം, ആദ്യ വർഷത്തെ ഹോസ്റ്റിംഗ് എന്നിവ ഉൾപ്പെടുന്നു.</span>
    </p>
    <p class="price-detail">Annual hosting renewal from the second year: ₹1,800 + applicable GST.
      <span class="price-detail-ml" lang="ml">രണ്ടാം വർഷം മുതൽ വാർഷിക ഹോസ്റ്റിംഗ് പുതുക്കൽ: ₹1,800 + ബാധകമായ GST.</span>
    </p>
    <a class="price-faq" href="{{ route('faq-charges') }}">See FAQ &amp; Charges →</a>
  </div>
</section>
<!-- EDITORIAL CONFIDENCE -->
<section class="care-section" id="editorial-care">
  <div class="section-hd">
    <span class="section-eyebrow">Editorial care / Editorial Preparation</span>
    <h2 class="section-title">Prepared With Editorial Care
      <span class="section-title-ml">ശ്രദ്ധാപൂർവമായ Editorial Preparation</span>
    </h2>
  </div>
  <div class="care-box">
    <p class="care-text">Every Jannayaks profile is prepared from information submitted by the individual concerned. Editing assisted by artificial intelligence (AI) and editorial review help us present that information clearly and professionally. The profile owner reviews and approves the final content before publication.
      <span class="care-text-ml" lang="ml">ഓരോ Jannayaks പ്രൊഫൈലും ബന്ധപ്പെട്ട വ്യക്തി നേരിട്ട് നൽകുന്ന വിവരങ്ങളുടെ അടിസ്ഥാനത്തിലാണ് തയ്യാറാക്കുന്നത്. AI സഹായത്തോടെയുള്ള Editing-ഉം Editorial Review-വും വിവരങ്ങൾ വ്യക്തമായും പ്രൊഫഷണലായും അവതരിപ്പിക്കാൻ സഹായിക്കുന്നു. പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് പ്രൊഫൈൽ ഉടമ അന്തിമ ഉള്ളടക്കം പരിശോധിച്ച് അംഗീകരിക്കുന്നു.</span>
    </p>
  </div>
</section>
<!-- RECOMMEND SOMEONE -->
<section class="rec-section" id="recommend">
  <div class="section-hd">
    <span class="section-eyebrow">Recommend / നിർദ്ദേശിക്കുക</span>
    <h2 class="section-title">Recommend Someone You Know
      <span class="section-title-ml">നിങ്ങൾക്കറിയാവുന്ന ഒരാളെ നിർദ്ദേശിക്കൂ</span>
    </h2>
  </div>
  <div class="rec-box">
    <p class="rec-text">Know someone whose achievements deserve recognition? Recommend them for Jannayaks.
      <span class="rec-text-ml" lang="ml">നേട്ടങ്ങൾ ശ്രദ്ധിക്കപ്പെടേണ്ട ഒരാളെ നിങ്ങൾക്കറിയാമോ? Jannayaks-ലേക്ക് നിർദ്ദേശിക്കൂ.</span>
    </p>
    <a class="rec-btn" href="{{ route('recommend.show') }}">Recommend Someone
      <span class="cta-ml" lang="ml">ഒരാളെ നിർദ്ദേശിക്കൂ</span>
    </a>
  </div>
</section>
<!-- FINAL CALL TO ACTION -->
<section class="final-section" id="create">
  <h2 class="final-hd">Your Story Deserves to Be Told
    <span class="final-hd-ml" lang="ml">നിങ്ങളുടെ നേട്ടങ്ങൾ ലോകമറിയട്ടെ</span>
  </h2>
  <p class="final-text">Create your professionally prepared Jannayaks profile and share your achievements with the world.
    <span class="final-text-ml" lang="ml">നിങ്ങളുടെ നേട്ടങ്ങൾ പ്രൊഫഷണലായി തയ്യാറാക്കിയ Jannayaks പ്രൊഫൈലിലൂടെ ലോകവുമായി പങ്കുവയ്ക്കൂ.</span>
  </p>
  <a class="final-btn" href="{{ route('apply') }}">Create Your Profile
    <span class="cta-ml" lang="ml">നിങ്ങളുടെ പ്രൊഫൈൽ സൃഷ്ടിക്കൂ</span>
  </a>
</section>
<!-- EDITORIAL AND INFORMATION NOTICE -->
<section style="border-top:1.5px solid var(--border);margin:34px auto 0;padding:18px 18px 6px;max-width:860px;">
  <div style="font-size:11.5px;color:var(--gray);line-height:1.65;text-align:center;">
    <strong style="display:block;color:var(--charcoal);font-size:12px;letter-spacing:.04em;margin-bottom:6px;">Editorial and Information Notice</strong>
    Jannayaks profiles are prepared from information and material supplied directly by the profile owners. Our editorial team uses artificial intelligence to assist with editing and reviews each profile for editorial quality and suitability. We do not independently verify every factual statement. Visitors should check information before relying on it. All applications are subject to editorial acceptance. If Jannayaks declines an application before publication, the full amount paid, including applicable taxes, will be refunded.
    <strong style="display:block;color:var(--charcoal);font-size:12px;letter-spacing:.04em;margin:10px 0 6px;font-family:var(--fm);">Editorial, വിവര അറിയിപ്പ്</strong>
    <span style="font-family:var(--fm);">Jannayaks-ലെ പ്രൊഫൈലുകൾ അതത് വ്യക്തികൾ നേരിട്ട് നൽകുന്ന വിവരങ്ങളുടെയും രേഖകളുടെയും അടിസ്ഥാനത്തിലാണ് തയ്യാറാക്കുന്നത്. ഞങ്ങളുടെ Editorial Team, Artificial Intelligence (AI) യുടെ സഹായത്തോടെ പ്രൊഫൈലുകൾ എഡിറ്റ് ചെയ്യുകയും പ്രസിദ്ധീകരണത്തിന് മുമ്പ് അവയുടെ ഗുണനിലവാരവും അനുയോജ്യതയും പരിശോധിക്കുകയും ചെയ്യുന്നു. എന്നാൽ എല്ലാ വസ്തുതകളും ഞങ്ങൾ സ്വതന്ത്രമായി സ്ഥിരീകരിക്കുന്നില്ല. വിവരങ്ങൾ ആശ്രയിക്കുന്നതിന് മുമ്പ് സന്ദർശകർ അവയുടെ കൃത്യത പരിശോധിക്കേണ്ടതാണ്. എല്ലാ അപേക്ഷകളും Editorial Acceptance-ന് വിധേയമാണ്. പ്രസിദ്ധീകരണത്തിന് മുമ്പ് Jannayaks ഒരു അപേക്ഷ നിരസിക്കുന്ന പക്ഷം, അടച്ച മുഴുവൻ തുകയും ബാധകമായ നികുതികൾ ഉൾപ്പെടെ തിരികെ നൽകുന്നതാണ്.</span>
    <a href="{{ route('legal.disclaimer') }}" style="display:inline-block;margin-top:8px;color:var(--gray);font-weight:600;">Read the full Disclaimer →</a>
  </div>
</section>
<!-- FOOTER -->
@include('partials.public-footer')
<script>
function switchIntroLang(lang){
  ['ml','en'].forEach(function(l){
    var on=l===lang, tab=document.getElementById('intro-tab-'+l);
    tab.setAttribute('aria-selected',on?'true':'false');
    tab.tabIndex=on?0:-1;
    document.getElementById('intro-panel-'+l).hidden=!on;
  });
}
document.querySelector('.intro-tabs').addEventListener('keydown',function(e){
  if(e.key!=='ArrowLeft'&&e.key!=='ArrowRight')return;
  var next=document.getElementById('intro-tab-en').getAttribute('aria-selected')==='true'?'ml':'en';
  switchIntroLang(next);
  document.getElementById('intro-tab-'+next).focus();
});
</script>
</body>
</html>
