{{-- Google Translate loader: include once per page, alongside partials.translate-control. --}}
@once
<div id="google_translate_element" style="display:none;"></div>
<script>
function googleTranslateElementInit(){
  new google.translate.TranslateElement({
    pageLanguage:'en',
    includedLanguages:'en,ml,hi,ta,kn,te,bn,gu,mr',
    layout:google.translate.TranslateElement.InlineLayout.SIMPLE,
    autoDisplay:false
  },'google_translate_element');
}
function setTranslateOpen(open){
  var menu=document.querySelector('.translate-dropdown');
  var btn=document.querySelector('.translate-btn');
  if(menu){menu.classList.toggle('open',open);}
  if(btn){btn.setAttribute('aria-expanded',open?'true':'false');}
}
function toggleTranslate(){
  var menu=document.querySelector('.translate-dropdown');
  setTranslateOpen(!(menu&&menu.classList.contains('open')));
}
document.addEventListener('click',function(e){
  if(!e.target.closest('.translate-wrap')){setTranslateOpen(false);}
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){setTranslateOpen(false);}
});
function setLang(lang){
  // Google may also write googtrans on the (parent) domain; clear every copy or "English" never sticks.
  var expired='googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
  var host=location.hostname;
  var parts=host.split('.');
  document.cookie=expired;
  document.cookie=expired+'; domain='+host;
  document.cookie=expired+'; domain=.'+host;
  if(parts.length>2){document.cookie=expired+'; domain=.'+parts.slice(-2).join('.');}
  if(lang!=='en'){document.cookie='googtrans=/en/'+lang+'; path=/';}
  location.reload();
}
</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
@endonce
