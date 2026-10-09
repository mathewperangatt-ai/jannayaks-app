{{-- Shared public footer — Jannayaks.
     Typographic, no logo block; grouped Platform/About/Support/Legal columns.
     Platform and legal links are live routes. ABOUT destinations and Help
     Centre are pending their own pages (kept per the approved footer
     structure; wire them when those pages exist). --}}
<footer class="jf-footer">
<style>
.jf-footer{background:#214d68;color:rgba(255,255,255,.82);padding:52px 22px 26px;margin-top:24px;font-family:'DM Sans',system-ui,sans-serif}
.jf-inner{max-width:1180px;margin:0 auto}
.jf-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr 1fr 1fr;gap:36px}
.jf-brand .jf-word{font:600 17px 'Fraunces','Cormorant Garamond',Georgia,serif;letter-spacing:.14em;color:#fff;margin:0 0 4px}
.jf-brand .jf-word small{display:block;font:500 8.5px 'DM Sans',sans-serif;letter-spacing:.22em;color:rgba(255,255,255,.5);margin-top:4px}
.jf-contact{list-style:none;margin:18px 0 0;padding:0}
.jf-contact li{margin:0 0 7px;font-size:13px;color:rgba(255,255,255,.72)}
.jf-contact a{color:rgba(255,255,255,.85);text-decoration:none;border-bottom:1px dotted rgba(255,255,255,.35)}
.jf-contact a:hover{color:#e8c48a;border-color:#e8c48a}
.jf-title{font:600 10px 'DM Sans',sans-serif;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.5);margin:4px 0 14px}
.jf-links{list-style:none;margin:0;padding:0}
.jf-links li{margin:0 0 9px}
.jf-links a{color:rgba(255,255,255,.82);text-decoration:none;font-size:13.5px}
.jf-links a:hover{color:#e8c48a}
.jf-bottom{border-top:1px solid rgba(255,255,255,.14);margin-top:42px;padding-top:18px;display:flex;flex-wrap:wrap;gap:10px 26px;justify-content:space-between;font-size:11.5px;color:rgba(255,255,255,.45)}
@media(max-width:960px){.jf-grid{grid-template-columns:1fr 1fr 1fr}.jf-brand{grid-column:1/-1}}
@media(max-width:560px){.jf-grid{grid-template-columns:1fr 1fr;gap:26px}.jf-footer{padding:42px 18px 22px}}
</style>
<div class="jf-inner">
    <div class="jf-grid">
        <div class="jf-brand">
            <p class="jf-word">JANNAYAKS<small>PEOPLE · PUBLIC LIFE · INDIA</small></p>
            <ul class="jf-contact">
                <li><a href="mailto:{{ config('jannayaks.contact.public_email') }}">{{ config('jannayaks.contact.public_email') }}</a></li>
                <li>jannayaks.in</li>
            </ul>
        </div>
        <div class="jf-col" role="navigation" aria-label="Platform">
            <h3 class="jf-title">Platform</h3>
            <ul class="jf-links">
                <li><a href="{{ route('gallery.index') }}">Gallery</a></li>
                <li><a href="{{ route('demo-profiles.index') }}">View Demo Profiles</a></li>
                <li><a href="{{ route('search.index') }}">Search</a></li>
                <li><a href="{{ route('apply') }}">Create Profile</a></li>
                <li><a href="{{ route('faq-charges') }}">FAQ &amp; Charges</a></li>
                <li><a href="{{ route('in-memoriam.index') }}">In Memoriam</a></li>
            </ul>
        </div>
        <div class="jf-col" role="navigation" aria-label="About">
            <h3 class="jf-title">About</h3>
            <ul class="jf-links">
                <li><a href="#">About Jannayaks</a></li>
                <li><a href="#">Political Neutrality</a></li>
                <li><a href="#">Identity Verification</a></li>
            </ul>
        </div>
        <div class="jf-col" role="navigation" aria-label="Support">
            <h3 class="jf-title">Support</h3>
            <ul class="jf-links">
                <li><a href="mailto:{{ config('jannayaks.contact.public_email') }}">Contact Us</a></li>
                <li><a href="#">Help Centre</a></li>
                <li><a href="{{ route('legal.privacy') }}">Privacy Policy</a></li>
                <li><a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a></li>
                <li><a href="{{ route('legal.grievance') }}">Grievance Redressal</a></li>
            </ul>
        </div>
        <div class="jf-col" role="navigation" aria-label="Legal">
            <h3 class="jf-title">Legal</h3>
            <ul class="jf-links">
                <li><a href="{{ route('legal.refund') }}">Refund &amp; Cancellation</a></li>
                <li><a href="{{ route('legal.disclaimer') }}">Disclaimer</a></li>
            </ul>
        </div>
    </div>
    <div class="jf-bottom">
        <span>© {{ date('Y') }} Jannayaks™ · Aurex Network</span>
    </div>
</div>
</footer>
