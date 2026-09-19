const app=document.querySelector('#app'), editor=document.querySelector('#editor'), detail=document.querySelector('#detail');
const adminPage=location.pathname==='/admin';
let birds=[],authenticated=false,toastTimer;
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const sexLabel={M:'Mascul',F:'Femelă',U:'Sex necunoscut'};
async function api(path,options={}) {
  const response=await fetch(path,{...options,headers:{'Content-Type':'application/json',...options.headers}});
  const data=await response.json();
  if(!response.ok) {if(response.status===401 && adminPage && path!=='/api/login'){authenticated=false;login();} throw new Error(data.error||'Cererea nu a putut fi finalizată.');}
  return data;
}
function toast(message){const box=document.querySelector('#toast');box.textContent=message;box.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>box.classList.remove('show'),4500);}
function imageUrl(b){return `/api/${adminPage&&authenticated?'admin/':''}pigeons/${b.id}/image?v=${encodeURIComponent(b.version||'public')}`;}
function photo(b,cls='card-image'){return b.has_image?`<img class="${cls}" src="${imageUrl(b)}" alt="${esc(b.name||b.ring)}" loading="lazy">`:`<div class="${cls} placeholder"><span aria-hidden="true">TV</span><small>Fotografie indisponibilă</small></div>`;}
function publicHome(){
  const featured=birds.find(b=>b.has_image);
  app.innerHTML=`<div class="page-heading"><div><p class="eyebrow">Team Vicov</p><h1>Porumbei. Origini. Performanță.</h1></div><a class="text-link" href="#catalog">Explorează catalogul →</a></div>
  <div class="home-lead"><section class="feature-story" aria-labelledby="feature-title"><div class="feature-copy"><p class="eyebrow">${featured?'Din catalogul nostru':'Pasiunea care ne aduce împreună'}</p><h2 id="feature-title">${featured?esc(featured.name||featured.ring):'Fiecare porumbel are o poveste.'}</h2><p>${featured?esc(featured.ring)+' · '+sexLabel[featured.sex]:'Descoperă porumbeii Team Vicov și generațiile din spatele fiecărui pedigree.'}</p>${featured?`<button class="primary" id="featured-bird">Descoperă porumbelul →</button>`:'<a class="button primary" href="#catalog">Vezi catalogul →</a>'}</div>${featured?photo(featured,'feature-photo'):'<div class="feature-mark" aria-hidden="true"><span>TV</span><small>TEAM VICOV / BUCOVINA</small></div>'}</section>
  <aside class="auction-panel" id="licitatii"><div class="panel-label">Licitații Team Vicov <span class="badge draft">În pregătire</span></div><h2>Următoarele licitații</h2><p>Aici vei găsi selecțiile de porumbei și calendarul licitațiilor Team Vicov.</p><div class="auction-status"><span aria-hidden="true">↗</span><div><strong>Nicio licitație deschisă</strong><p>Datele vor fi anunțate aici.</p></div></div><a class="text-link" href="#catalog">Descoperă porumbeii din catalog →</a></aside></div>
  <section id="catalog"><div class="section-head"><div><p class="eyebrow">Catalog Team Vicov</p><h2>Porumbeii noștri <span class="count">${birds.length}</span></h2></div></div><div class="catalog-toolbar"><input id="search" class="search" type="search" aria-label="Caută porumbei" placeholder="Caută după serie sau nume…"><label class="filter-label">Sex<select id="sex-filter"><option value="">Toți porumbeii</option><option value="M">Masculi</option><option value="F">Femele</option><option value="U">Sex necunoscut</option></select></label><span id="result-count" class="result-count" role="status" aria-live="polite"></span></div><div class="cards" id="bird-list"></div></section>
  <section class="catalog-guide" aria-label="Despre catalog"><div><span class="guide-number">01</span><h3>Porumbei</h3><p>Fotografii și informații pentru fiecare porumbel publicat în catalog.</p></div><div><span class="guide-number">02</span><h3>Origini</h3><p>Descoperă părinții și bunicii documentați în fișa de pedigree.</p></div><div><span class="guide-number">03</span><h3>Rezultate</h3><p>Consultă prezentarea și rezultatele adăugate în fiecare fișă.</p></div></section>`;
  document.querySelector('#search').addEventListener('input',renderList);
  document.querySelector('#sex-filter').addEventListener('change',renderList);
  if(featured)document.querySelector('#featured-bird').onclick=()=>showDetail(featured);
  renderList();
}
function login(){app.innerHTML=`<section class="login"><p class="eyebrow">Spațiu privat</p><h1>Administrare Team Vicov</h1><p>Intră în registru pentru a gestiona porumbeii și pedigree-urile.</p><form id="login-form"><label>Parola administratorului<input name="password" type="password" autocomplete="current-password" required maxlength="1024"></label><p class="error" role="alert"></p><button class="primary" type="submit">Autentificare →</button></form></section>`;
  document.querySelector('#login-form').addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,button=form.querySelector('button');button.disabled=true;form.querySelector('.error').textContent='';try{await api('/api/login',{method:'POST',body:JSON.stringify({password:form.password.value})});authenticated=true;await load();}catch(e){form.querySelector('.error').textContent=e.message;}finally{button.disabled=false;}});
}
function dashboard(){
  const published=birds.filter(b=>b.published).length;
  app.innerHTML=`<div class="admin-head"><div><p class="eyebrow">Administrare · Team Vicov</p><h1>Registrul de porumbei</h1></div><div class="actions"><button id="logout">Ieșire</button><button class="primary" id="add-bird">+ Adaugă porumbel</button></div></div><div class="stats"><div class="stat"><strong>${birds.length}</strong><span>Porumbei în registru</span></div><div class="stat"><strong>${published}</strong><span>Publicați în catalog</span></div><div class="stat"><strong>${birds.length-published}</strong><span>Fișe private</span></div></div><div class="section-head"><div><h2>Toți porumbeii</h2></div><input id="search" class="search" type="search" aria-label="Caută în registru" placeholder="Serie, nume sau proprietar…"></div><div id="bird-list"></div><p class="tip">Fișele noi sunt private. Bifează „Publică în catalog” când sunt pregătite. Proprietarul și notele interne sunt vizibile doar în administrare.</p>`;
  document.querySelector('#search').addEventListener('input',renderList);
  document.querySelector('#add-bird').onclick=()=>editBird();
  document.querySelector('#logout').onclick=async()=>{try{await api('/api/logout',{method:'POST',body:'{}'});authenticated=false;birds=[];login();}catch(e){toast(e.message);}};
  renderList();
}
function renderList(){
  const query=(document.querySelector('#search')?.value||'').trim().toLocaleLowerCase('ro');
  const selectedSex=document.querySelector('#sex-filter')?.value||'';
  const filtered=birds.filter(b=>(!selectedSex||b.sex===selectedSex)&&[b.ring,b.name,adminPage?b.owner:''].join(' ').toLocaleLowerCase('ro').includes(query));
  const resultCount=document.querySelector('#result-count');
  if(resultCount)resultCount.textContent=`${filtered.length} din ${birds.length} porumbei`;
  const container=document.querySelector('#bird-list');
  if(!filtered.length){container.innerHTML=`<div class="empty"><h3>${query||selectedSex?'Niciun rezultat':adminPage?'Primul porumbel, prima poveste.':'Catalogul se pregătește.'}</h3><p>${query||selectedSex?'Încearcă un alt nume, o altă serie sau schimbă filtrul.':adminPage?'Adaugă un porumbel, apoi completează fotografia, rezultatele și legăturile cu părinții.':'Vom publica aici porumbeii, fotografiile și pedigree-urile Team Vicov.'}</p></div>`;return;}
  if(adminPage){container.innerHTML=`<div class="table-wrap"><table><thead><tr><th>Porumbel</th><th>Sex</th><th>Proprietar</th><th>Vizibilitate</th><th>Fișă</th></tr></thead><tbody>${filtered.map(b=>`<tr><td><div class="table-bird">${b.has_image?`<img src="${imageUrl(b)}" alt="" loading="lazy">`:''}<div><strong>${esc(b.ring)}</strong><small>${esc(b.name||'Fără nume')}</small></div></div></td><td>${sexLabel[b.sex]}</td><td>${esc(b.owner||'—')}</td><td><span class="badge ${b.published?'':'draft'}">${b.published?'Public':'Privat'}</span></td><td><div class="actions"><button data-edit="${b.id}">Editează</button><button data-detail="${b.id}" aria-label="Pedigree ${esc(b.ring)}">Pedigree</button></div></td></tr>`).join('')}</tbody></table></div>`;}
  else container.innerHTML=filtered.map(b=>`<button class="card" data-detail="${b.id}">${photo(b)}<div class="card-content"><span class="ring">${esc(b.ring)}</span><h3>${esc(b.name||b.ring)}</h3><div class="card-bottom"><span>${sexLabel[b.sex]}${b.color?` · ${esc(b.color)}`:''}</span><span>Vezi fișa ↗</span></div></div></button>`).join('');
  container.querySelectorAll('[data-edit]').forEach(el=>el.onclick=()=>editBird(birds.find(b=>b.id===el.dataset.edit)));
  container.querySelectorAll('[data-detail]').forEach(el=>el.onclick=()=>showDetail(birds.find(b=>b.id===el.dataset.detail)));
}
function parentOptions(sex,current,id){return `<option value="">Nespecificat</option>`+birds.filter(b=>b.id!==id && (b.sex===sex||b.sex==='U')).map(b=>`<option value="${b.id}" ${b.id===current?'selected':''}>${esc(b.ring)}${b.name?' · '+esc(b.name):''}</option>`).join('');}
function editBird(b={}){
  editor.innerHTML=`<div class="dialog-head"><div><p class="eyebrow">Fișă porumbel</p><h2 id="editor-title">${b.id?'Editează porumbelul':'Adaugă un porumbel'}</h2></div><button class="close" type="button" aria-label="Închide">×</button></div><form id="bird-form"><div class="form-grid"><label>Seria inelului *<input name="ring" required maxlength="64" value="${esc(b.ring)}" placeholder="RO 25-008011"></label><label>Nume<input name="name" maxlength="100" value="${esc(b.name)}" placeholder="Numele porumbelului"></label><label>Sex<select name="sex">${Object.entries(sexLabel).map(([v,label])=>`<option value="${v}" ${(b.sex||'U')===v?'selected':''}>${label}</option>`).join('')}</select></label><label>Culoare<input name="color" maxlength="100" value="${esc(b.color)}"></label><label class="wide">Proprietar <span class="helper">Doar pentru evidența internă</span><input name="owner" maxlength="200" value="${esc(b.owner)}"></label><label>Tată<select name="father_id">${parentOptions('M',b.father_id,b.id)}</select></label><label>Mamă<select name="mother_id">${parentOptions('F',b.mother_id,b.id)}</select></label><p class="helper wide">Adaugă mai întâi părinții ca fișe separate, apoi selectează-i aici. În catalogul public apar doar părinții publicați.</p><label class="wide">Rezultate și prezentare publică<textarea name="results" maxlength="10000">${esc(b.results)}</textarea></label><label class="wide">Note interne<textarea name="notes" maxlength="10000">${esc(b.notes)}</textarea></label><label class="wide">${b.has_image?'Înlocuiește fotografia':'Fotografie'}<input name="photo" type="file" accept="image/jpeg,image/png,image/webp"><span class="helper">JPG, PNG sau WebP · maximum 5 MB</span></label>${b.has_image?'<label class="checkbox wide"><input type="checkbox" name="remove_photo">Elimină fotografia existentă</label>':''}<label class="checkbox wide"><input type="checkbox" name="published" ${b.published?'checked':''}>Publică în catalog</label></div><p class="error" role="alert"></p><div class="form-footer"><button type="button" class="cancel">Anulează</button><button type="submit" class="primary">Salvează fișa</button></div></form>`;
  const close=()=>editor.close();editor.querySelector('.close').onclick=close;editor.querySelector('.cancel').onclick=close;
  editor.showModal();
  editor.querySelector('form').onsubmit=async event=>{
    event.preventDefault();const form=event.currentTarget,button=form.querySelector('[type=submit]'),errorBox=form.querySelector('.error');button.disabled=true;errorBox.textContent='';
    try{
      const data=Object.fromEntries(new FormData(form));delete data.photo;delete data.remove_photo;data.published=form.published.checked;data.version=b.version;
      const file=form.photo.files[0];let uploaded;
      if(file){if(file.size>5*1024*1024)throw new Error('Fotografia trebuie să aibă cel mult 5 MB.');if(!['image/jpeg','image/png','image/webp'].includes(file.type))throw new Error('Folosește JPG, PNG sau WebP.');uploaded=await new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(r.result);r.onerror=()=>reject(new Error('Nu am putut citi fotografia.'));r.readAsDataURL(file);});}
      const saved=await api('/api/admin/pigeons'+(b.id?'/'+b.id:''),{method:b.id?'PUT':'POST',body:JSON.stringify(data)});
      b=saved;
      if(uploaded || form.remove_photo?.checked){
        try{const result=await api(`/api/admin/pigeons/${saved.id}/image`,{method:'PUT',body:JSON.stringify({image:uploaded||null,version:saved.version})});b.version=result.version;}
        catch(e){throw new Error('Fișa a fost salvată, dar fotografia nu: '+e.message);}
      }
      editor.close();await load();toast('Fișa a fost salvată.');
    }catch(e){errorBox.textContent=e.message;}finally{button.disabled=false;}
  };
}
function ancestor(id,label,depth=0){const b=birds.find(p=>p.id===id);return `<div class="ancestor"><small>${label}</small><p><strong>${b?esc(b.ring):'Nespecificat'}</strong></p>${b?.name?`<p>${esc(b.name)}</p>`:''}${b&&depth===0?`<div class="grandparents">${ancestor(b.father_id,'Tată',1)}${ancestor(b.mother_id,'Mamă',1)}</div>`:''}</div>`;}
function showDetail(b){
  detail.innerHTML=`<div class="dialog-head"><div><p class="eyebrow">${esc(b.ring)}</p><h2 id="detail-title">${esc(b.name||b.ring)}</h2></div><button class="close" aria-label="Închide">×</button></div>${photo(b,'detail-photo card-image')}<div class="metadata"><span>${sexLabel[b.sex]}</span>${b.color?`<span>${esc(b.color)}</span>`:''}</div>${b.results?`<h3>Rezultate & prezentare</h3><p class="result-text">${esc(b.results)}</p>`:''}<h3>Pedigree · două generații</h3><div class="pedigree">${ancestor(b.father_id,'Tată')}${ancestor(b.mother_id,'Mamă')}</div>`;
  detail.querySelector('.close').onclick=()=>detail.close();detail.showModal();
}
async function load(){
  if(adminPage&&!authenticated){const result=await api('/api/session');authenticated=result.authenticated;if(!authenticated)return login();}
  birds=await api(adminPage?'/api/admin/pigeons':'/api/pigeons');
  if(adminPage)dashboard();else publicHome();
}
load().catch(e=>{app.innerHTML=`<div class="empty"><h3>Nu am putut încărca pagina.</h3><p>${esc(e.message)}</p><button id="retry">Reîncearcă</button></div>`;document.querySelector('#retry').onclick=()=>location.reload();});
