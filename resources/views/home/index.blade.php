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
<title>Jannayaks™ — Kerala's Public Presence Platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=DM+Sans:wght@300;400;500;600&family=Noto+Serif+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#0F1F3D;--saffron:#C65102;--white:#FFFFFF;--off:#F8F6F2;
  --gray:#6B7280;--light:#F3F4F6;--charcoal:#1F2937;--border:#E5E7EB;
  --gold:#B8860B;--goldbg:#FFFBEB;--greenbg:#F0FDF4;--green:#1A6B3C;
  --fd:'Cormorant Garamond',Georgia,serif;--fb:'DM Sans',sans-serif;--fm:'Noto Serif Malayalam',serif;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{background:transparent;color:var(--charcoal);font-family:var(--fb);-webkit-font-smoothing:antialiased;position:relative;overflow-x:hidden;}
.construction-banner{background:var(--navy);color:#fff;text-align:center;font-family:var(--fb);font-size:13px;letter-spacing:2px;text-transform:uppercase;padding:10px;position:fixed;top:0;left:0;right:0;z-index:9999;}
body{padding-top:38px;}
.container{width:100%;max-width:1100px;margin:0 auto;padding:0 22px;}
.ml{font-family:var(--fm);display:block;font-size:.85em;color:var(--gray);margin-top:2px;}
/* NAV */
nav{background:var(--white);border-bottom:2px solid var(--saffron);position:sticky;top:38px;z-index:100;box-shadow:0 1px 12px rgba(0,0,0,.06);}
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
.nav-cta:hover{background:#a84400;}
/* HERO */
.hero{background:linear-gradient(135deg,#F8F6F2 0%,#EEF0F8 100%);padding:80px 22px 88px;text-align:center;border-bottom:1px solid var(--border);position:relative;overflow:hidden;}
.hero::before{content:'';position:absolute;top:-40px;right:-40px;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(198,81,2,.06),transparent);pointer-events:none;}
.hero::after{content:'';position:absolute;bottom:-60px;left:-60px;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(15,31,61,.04),transparent);pointer-events:none;}
.hero-main-heading{text-align:center;max-width:900px;margin:0 auto 30px;position:relative;}
.hero-main-heading h1{font-family:var(--fd);font-size:clamp(38px,7vw,68px);line-height:1.12;font-weight:700;color:var(--navy);margin:0 0 8px;overflow-wrap:anywhere;}
.hero-main-heading .hero-ml{font-family:var(--fm);font-size:clamp(22px,4vw,38px);line-height:1.35;color:var(--navy);margin:0;opacity:.7;overflow-wrap:anywhere;}
.hero-intro{max-width:760px;margin:0 auto 0;position:relative;}
.hero-intro p{font-size:17px;line-height:1.65;font-weight:300;color:var(--charcoal);}
.hero-intro .hero-ml{font-family:var(--fm);font-size:14px;line-height:1.7;color:var(--gray);margin-top:8px;display:block;}
/* SEARCH BOX */
.search-section{background:transparent;padding:56px 22px;border-bottom:1px solid var(--border);}
.search-box{background:white;border:1.5px solid var(--border);border-radius:12px;padding:32px;max-width:740px;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,.06);}
.search-title{font-family:var(--fd);font-size:22px;font-weight:700;color:var(--navy);margin-bottom:4px;}
.search-title-ml{font-family:var(--fm);font-size:14px;color:var(--gray);display:block;margin-bottom:22px;}
.search-tabs{display:flex;gap:4px;background:var(--light);padding:4px;border-radius:8px;margin-bottom:20px;}
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
.hiw{padding:80px 22px;background:transparent;}
.section-hd{text-align:center;margin-bottom:52px;}
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
.tbtn-d:hover{background:#9a7009;}
.tbtn-a{background:var(--navy);color:white;border:none;}
.tbtn-a:hover{background:var(--saffron);}
.tbtn-e{background:transparent;color:var(--navy);border:1.5px solid var(--navy);}
.tbtn-e:hover{background:var(--navy);color:white;}
/* URL SECTION */
.url-section{padding:68px 22px;background:transparent;border-top:1px solid var(--border);}
.url-box{background:white;border:1.5px solid var(--border);border-radius:12px;padding:32px;max-width:680px;margin:0 auto;}
.url-title{font-family:var(--fd);font-size:22px;font-weight:700;color:var(--navy);margin-bottom:4px;}
.url-title-ml{font-family:var(--fm);font-size:14px;color:var(--gray);display:block;margin-bottom:20px;}
.url-examples{display:flex;flex-direction:column;gap:10px;margin-bottom:20px;}
.url-example{background:var(--light);border-radius:6px;padding:12px 16px;font-family:monospace;font-size:13px;color:var(--navy);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;overflow-wrap:anywhere;word-break:break-word;}
.url-badge{font-size:10px;font-family:var(--fb);background:var(--navy);color:white;padding:2px 8px;border-radius:10px;letter-spacing:.5px;flex-shrink:0;}
.url-note{font-size:13px;color:var(--gray);line-height:1.7;margin-bottom:16px;overflow-wrap:anywhere;}
.url-note-ml{font-family:var(--fm);font-size:12px;color:var(--gray);display:block;margin-top:4px;line-height:1.7;}
.url-custom{background:var(--greenbg);border:1px solid #BBF7D0;border-radius:8px;padding:14px 16px;margin-top:12px;}
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
.mem-section{padding:72px 22px;background:var(--navy);text-align:center;}
.mem-title{font-family:var(--fd);font-size:26px;font-weight:700;color:white;margin-bottom:4px;}
.mem-title-ml{font-family:var(--fm);font-size:16px;color:rgba(255,255,255,.65);display:block;margin-bottom:14px;}
.mem-text{font-size:14px;color:rgba(255,255,255,.75);line-height:1.75;max-width:560px;margin:0 auto 6px;overflow-wrap:anywhere;}
.mem-text-ml{font-family:var(--fm);font-size:12px;color:rgba(255,255,255,.6);line-height:1.75;display:block;max-width:560px;margin:0 auto 24px;overflow-wrap:anywhere;}
.mem-btn{background:var(--gold);color:white;border:none;padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--fb);transition:all .2s;text-decoration:none;display:inline-block;min-height:44px;line-height:1.4;}
.mem-btn:hover{background:#9a7009;}
/* FOOTER */
footer{background:var(--navy);padding:48px 22px 28px;}
.footer-grid{display:grid;grid-template-columns:1fr;gap:32px;margin-bottom:36px;max-width:1100px;margin-left:auto;margin-right:auto;}
.f-logo{display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;padding:6px;background:#fff;border-radius:8px;}
.f-logo img{height:64px;width:auto;display:block;}
.f-about{font-size:13px;color:rgba(255,255,255,.45);line-height:1.65;max-width:240px;margin-bottom:14px;}
.f-about-ml{font-family:var(--fm);font-size:11px;color:rgba(255,255,255,.35);display:block;margin-top:4px;line-height:1.65;}
.f-contact a{display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.45);text-decoration:none;margin-bottom:5px;transition:color .2s;min-height:40px;}
.f-contact a:hover{color:var(--saffron);}
.f-col-title{font-size:10px;font-weight:600;color:rgba(255,255,255,.7);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:12px;}
.f-links{list-style:none;display:flex;flex-direction:column;gap:8px;}
.f-links a{font-size:12px;color:rgba(255,255,255,.4);text-decoration:none;transition:color .2s;display:inline-flex;align-items:center;min-height:40px;}
.f-links a:hover{color:var(--saffron);}
.f-more-note{font-size:11px;color:rgba(255,255,255,.35);margin-top:10px;line-height:1.5;max-width:180px;}
.f-bottom{border-top:1px solid rgba(255,255,255,.08);padding-top:20px;display:flex;flex-direction:column;gap:5px;align-items:center;text-align:center;max-width:1100px;margin:0 auto;}
.f-copy,.f-tag{font-size:11px;color:rgba(255,255,255,.25);line-height:1.5;overflow-wrap:anywhere;}
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
  .nav-login,.nav-cta{padding:8px 12px;font-size:12px;}
  .hero{padding:48px 16px 52px;}
  .hero-main-heading{margin-bottom:0;}
  .hero-main-heading h1{font-size:clamp(26px,7.2vw,38px);line-height:1.2;}
  .hero-main-heading .hero-ml{font-size:clamp(16px,4.6vw,22px);line-height:1.4;margin-top:10px;}
  .search-section{padding:36px 16px 40px;}
  .search-box{padding:20px 16px;}
  .search-tabs{flex-direction:column;gap:6px;padding:6px;}
  .search-tab{flex:none;width:100%;text-align:left;padding:12px 14px;font-size:13px;min-height:48px;}
  .home-actions{flex-direction:column;align-items:stretch;gap:12px;margin-top:20px;max-width:740px;margin-left:auto;margin-right:auto;}
  .home-action{display:flex;align-items:center;justify-content:center;min-height:48px;padding:14px 16px;border:1.5px solid var(--navy);border-radius:8px;border-bottom:1.5px solid var(--navy);font-size:15px;background:white;}
  .home-action:hover{color:white;background:var(--navy);border-color:var(--navy);}
  .hiw{padding:56px 16px;}
  .section-hd{margin-bottom:32px;}
  .step{gap:14px;padding:18px 0;}
  .step-title{font-size:16px;}
  .step-title-ml{font-size:13px;}
  .step-text{font-size:14px;line-height:1.7;}
  .ep-section{padding:36px 16px 12px;}
  .url-section{padding:48px 16px;}
  .url-box{padding:22px 16px;}
  .url-example{font-size:12px;padding:12px 14px;}
  .mem-section{padding:52px 16px;}
  .mem-title{font-size:clamp(22px,6vw,26px);}
  .mem-text,.mem-text-ml{font-size:14px;}
  .mem-text-ml{font-size:13px;}
  footer{padding:40px 16px 24px;}
  .f-logo img{height:52px;}
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
  .footer-grid{grid-template-columns:2fr 1fr 1fr;}
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
  .footer-grid{grid-template-columns:2fr 1fr 1fr 1fr 1fr;}
}
</style>
</head>
<body>
<div class="construction-banner">Under Construction</div>
<!-- GOOGLE TRANSLATE (hidden, triggered by button) -->
<div id="google_translate_element" style="display:none;"></div>
<script>
function googleTranslateElementInit(){
  new google.translate.TranslateElement({
    pageLanguage:'en',
    includedLanguages:'ml,hi,ta,kn,te,bn,gu,mr',
    layout:google.translate.TranslateElement.InlineLayout.SIMPLE,
    autoDisplay:false
  },'google_translate_element');
}
function toggleTranslate(){
  document.querySelector('.translate-dropdown').classList.toggle('open');
}
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
  if(!e.target.closest('.translate-wrap'))
    document.querySelector('.translate-dropdown').classList.remove('open');
  if(!e.target.closest('#site-nav'))
    closeNavMenu();
});
function setLang(lang){
  document.cookie='googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
  document.cookie='googtrans=/en/'+lang+'; path=/;';
  location.reload();
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
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
      <li><a href="{{ route('gallery.index') }}">View Demo Profiles</a></li>
      <li><a class="nav-login-mobile" href="{{ route('login') }}">Sign In</a></li>
    </ul>
    <div class="nav-right">
      <div class="translate-wrap">
        <button type="button" class="translate-btn" onclick="toggleTranslate()" aria-label="Translate">🌐 <span class="translate-label">Translate ▾</span></button>
        <div class="translate-dropdown">
          <a href="#" onclick="setLang('ml');return false;">മലയാളം</a>
          <a href="#" onclick="setLang('hi');return false;">हिन्दी</a>
          <a href="#" onclick="setLang('ta');return false;">தமிழ்</a>
        </div>
      </div>
      <a class="nav-login" href="{{ route('login') }}">Sign In</a>
      <a class="nav-cta" href="{{ route('apply') }}">Create Profile</a>
    </div>
  </div>
</nav>
<!-- HERO -->
<section class="hero">
  <div class="hero-main-heading">
    <h1>A digital gallery of people's leaders from all walks of life, documenting their lives and contributions.</h1>
    <span class="hero-ml">സമൂഹത്തിന്റെ വിവിധ മേഖലകളിലെ ജനനായകരുടെ ജീവിതവും സമൂഹത്തിനുള്ള സംഭാവനകളും രേഖപ്പെടുത്തുന്ന ഒരു ഡിജിറ്റൽ ഗാലറി.</span>
  </div>
</section>
<!-- SEARCH -->
<section class="search-section" id="search">
  <div class="search-box">
    <div class="search-title">Search</div>
    <span class="search-title-ml">കണ്ടെത്തൂ</span>
    <div class="search-tabs">
      <button class="search-tab active" onclick="switchTab(this,'name')">🔍 By Name / പേര് പ്രകാരം</button>
      <button class="search-tab" onclick="switchTab(this,'area')">📍 By Area / പ്രദേശം പ്രകാരം</button>
      <button class="search-tab" onclick="switchTab(this,'pin')">📮 By Pin Code / പിൻകോഡ് പ്രകാരം</button>
    </div>
    <div id="tab-name">
      <form method="get" action="{{ route('search.index') }}" id="home-search-form">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input class="search-input" type="search" name="q" placeholder="Type name, ward, panchayat... / പേര്, വാർഡ്, പഞ്ചായത്ത്..." maxlength="200">
        </div>
      </form>
    </div>
    <div id="tab-area" style="display:none;">
      <div class="cascade-row">
        <select class="cascade-select"><option>Kerala ▾</option></select>
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
    <button type="submit" form="home-search-form" class="search-btn">Search — ലോകത്തെവിടെ നിന്നും തിരയൂ 🌍</button>
    <p class="search-note">Search is open worldwide. / ലോകത്തെവിടെ നിന്നും തിരയാവുന്നതാണ്.</p>
  </div>
  <div class="home-actions" aria-label="Quick links">
    <a class="home-action" href="{{ route('gallery.index') }}">View Demo Profiles →</a>
    <a class="home-action" href="{{ route('faq-charges') }}">FAQ &amp; Charges →</a>
  </div>
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
<!-- EDITORIAL PREPARATION -->
<section class="ep-section">
  <div class="container" style="max-width:680px;text-align:center;">
    <h3 style="font-family:var(--fd);font-size:20px;font-weight:700;color:var(--navy);margin-bottom:6px;">Editorial Preparation</h3>
    <p style="font-size:14px;color:var(--charcoal);line-height:1.7;">Every profile is prepared by our human editors, assisted by Artificial Intelligence.</p>
    <span style="font-family:var(--fm);font-size:12px;color:var(--gray);line-height:1.7;display:block;margin-top:4px;">നിങ്ങളുടെ വിവരണം തയ്യാറാക്കാൻ മാനുഷിക ബുദ്ധിക്കു പുറമെ നിർമ്മിതബുദ്ധിയുടെ സഹായവും ഞങ്ങൾ ഉപയോഗിക്കുന്നു.</span>
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
        jannayaks.in/<strong>sukumaraluva</strong>
        <span class="url-badge">Name + Place</span>
      </div>
      <div class="url-example">
        jannayaks.in/<strong>jacobvaliyaveed</strong>
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
  <span class="mem-title-ml">സ്മരണാഞ്ജലി</span>
  <p class="mem-text">A special section is provided for popular personalities who have passed away. A dignified memorial page for someone who has passed — open to anyone, prepared with the same editorial care as a living profile. For details, write to founder@jannayaks.in.</p>
  <span class="mem-text-ml">യശശരീരുടെ ഓർമക്കായ്‌ ഒരു സ്ഥിരവും അന്തസ്സുള്ളതുമായ അനുസ്മരണ താൾ — ജീവിച്ചിരിക്കുന്ന പ്രൊഫൈലിന്റെ അതേ എഡിറ്റോറിയൽ ശ്രദ്ധയോടെ തയ്യാറാക്കുന്നത്.</span>
  <div>
    <a class="mem-btn" href="{{ route('in-memoriam.index') }}">Create a Memorial</a>
  </div>
</section>
<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="f-logo"><img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks"></div>
        <div class="f-contact">
          <a href="mailto:hello@jannayaks.in">✉ hello@jannayaks.in</a>
          <a href="{{ route('home') }}">🌐 jannayaks.in</a>
        </div>
      </div>
      <div>
        <div class="f-col-title">Platform</div>
        <ul class="f-links">
          <li><a href="{{ route('gallery.index') }}">Browse Members</a></li>
          <li><a href="{{ route('apply') }}">Create Profile</a></li>
          <li><a href="{{ route('faq-charges') }}">FAQ &amp; Charges</a></li>
          <li><a href="{{ route('gallery.index') }}">View Demo Profiles</a></li>
        </ul>
      </div>
      <div>
        <div class="f-col-title">Support</div>
        <ul class="f-links">
          <li><a href="mailto:hello@jannayaks.in">Contact Us</a></li>
          <li><a href="#">Help Centre</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
        </ul>
      </div>
      <div>
        <div class="f-col-title">About</div>
        <ul class="f-links">
          <li><a href="#">About Jannayaks</a></li>
          <li><a href="#">Political Neutrality</a></li>
          <li><a href="#">Identity Verification</a></li>
        </ul>
      </div>
      <div>
        <div class="f-col-title">More from Jannayaks</div>
        <ul class="f-links">
          <li><a href="{{ route('gallery.index') }}">Directories</a></li>
          <li><a href="{{ route('in-memoriam.index') }}">In Memoriam</a></li>
        </ul>
        <p class="f-more-note">Rates on request. Contact us directly.</p>
      </div>
    </div>
    <div class="f-bottom">
      <div class="f-copy">© 2026 Jannayaks™ · Aurex Network. All rights reserved.</div>
      <div class="f-tag">Apolitical. Verified.</div>
    </div>
  </div>
</footer>
<script>
function switchTab(btn,tab){
  document.querySelectorAll('.search-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  ['name','area','pin'].forEach(t=>{
    document.getElementById('tab-'+t).style.display = t===tab ? 'block' : 'none';
  });
}
</script>
</body>
</html>
