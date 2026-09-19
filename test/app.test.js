import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { once } from 'node:events';
import { createApp } from '../src/server.js';
const password='test-only-long-password-2026';
const origin='http://localhost:3000';
const bird=(ring,extra={})=>({ring,name:'',sex:'U',color:'',owner:'private owner',notes:'private notes',results:'',published:false,father_id:null,mother_id:null,...extra});
test('catalog, authorization, pedigree integrity, photos and persistence',async t=>{
  const dir=await mkdtemp(join(tmpdir(),'teamvicov-test-'));
  let server,base,cookie;
  async function start(){server=await createApp({origin,password,dataDir:dir});server.listen(0,'127.0.0.1');await once(server,'listening');base=`http://127.0.0.1:${server.address().port}`;}
  async function stop(){await new Promise((resolve,reject)=>server.close(e=>e?reject(e):resolve()));}
  const request=async(path,method='GET',data,auth=true,requestOrigin=origin)=>{
    const r=await fetch(base+path,{method,headers:{'Content-Type':'application/json',Origin:requestOrigin,...(auth&&cookie?{Cookie:cookie}:{})},body:data===undefined?undefined:JSON.stringify(data)});
    const value=await r.json();return {status:r.status,value,headers:r.headers};
  };
  try{
    await start();
    await t.test('unauthenticated access and cross-origin requests rejected',async()=>{
      assert.equal((await request('/api/admin/pigeons')).status,401);
      assert.equal((await request('/api/admin/pigeons','POST',bird('RO 1'))).status,401);
      assert.equal((await request('/api/login','POST',{password},false,'https://evil.example')).status,403);
      assert.equal((await request('/api/login','POST',{password:'wrong'},false)).status,401);
      const login=await request('/api/login','POST',{password},false);assert.equal(login.status,200);cookie=login.headers.get('set-cookie').split(';')[0];
      assert.match(login.headers.get('set-cookie'),/HttpOnly/);assert.match(login.headers.get('set-cookie'),/SameSite=Strict/);
    });
    let father,mother,child;
    await t.test('private by default; duplicates rejected; notes and owners never public',async()=>{
      father=(await request('/api/admin/pigeons','POST',bird('RO 20-000001',{sex:'M'}))).value;
      mother=(await request('/api/admin/pigeons','POST',bird('RO 20-000002',{sex:'F',published:true}))).value;
      const created=await request('/api/admin/pigeons','POST',bird('RO 25-000003',{name:'<script>alert(1)</script>',published:true,father_id:father.id,mother_id:mother.id}));
      assert.equal(created.status,201);child=created.value;
      assert.equal((await request('/api/admin/pigeons','POST',bird('ro 25-000003'))).status,409);
      const list=(await request('/api/pigeons','GET',undefined,false)).value;
      assert.equal(list.length,2);const pub=list.find(p=>p.id===child.id);assert.equal(pub.father_id,null);assert.equal(pub.mother_id,mother.id);
      assert.equal('owner' in pub,false);assert.equal('notes' in pub,false);
      assert.equal((await request('/api/admin/pigeons','GET',undefined,false)).status,401);
    });
    await t.test('ancestry cycles, incompatible sex and stale edits rejected',async()=>{
      assert.equal((await request('/api/admin/pigeons/'+father.id,'PUT',{...father,published:false,father_id:child.id})).status,400);
      assert.equal((await request('/api/admin/pigeons/'+father.id,'PUT',{...father,published:false,sex:'F'})).status,400);
      assert.equal((await request('/api/admin/pigeons/'+child.id,'PUT',{...child,published:true,version:99})).status,409);
      const changed=await request('/api/admin/pigeons/'+child.id,'PUT',{...child,published:true,results:'Loc 1 — test'});assert.equal(changed.status,200);child=changed.value;
    });
    await t.test('photo signatures and private image authorization',async()=>{
      assert.equal((await request('/api/admin/pigeons/'+child.id+'/image','PUT',{image:'data:image/png;base64,'+Buffer.from('not a real image').toString('base64'),version:child.version})).status,400);
      const png='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZb8AAAAASUVORK5CYII=';
      assert.equal((await request('/api/admin/pigeons/'+father.id+'/image','PUT',{image:png,version:father.version})).status,200);
      assert.equal((await request('/api/pigeons/'+father.id+'/image','GET',undefined,false)).status,404);
      assert.equal((await fetch(base+'/api/admin/pigeons/'+father.id+'/image',{headers:{Cookie:cookie}})).status,200);
    });
    await t.test('health and static security headers',async()=>{
      assert.equal((await request('/healthz')).value.status,'ok');
      const r=await fetch(base+'/');assert.equal(r.status,200);assert.match(r.headers.get('content-security-policy'),/frame-ancestors 'none'/);assert.match(await r.text(),/Team Vicov/);
    });
    await t.test('logout invalidates session',async()=>{
      assert.equal((await request('/api/logout','POST',{})).status,200);assert.equal((await request('/api/admin/pigeons')).status,401);
    });
    await stop();await start();
    await t.test('data survives restart and old sessions do not',async()=>{
      const list=(await request('/api/pigeons','GET',undefined,false)).value;
      assert.equal(list.length,2);assert.equal(list.find(p=>p.id===child.id).results,'Loc 1 — test');
      assert.equal((await request('/api/admin/pigeons')).status,401);
    });
  }finally{if(server?.listening)await stop();await rm(dir,{recursive:true,force:true});}
});
test('production requires HTTPS and a strong administrator password',async()=>{
  await assert.rejects(()=>createApp({origin,password,production:true}),/https/);
  await assert.rejects(()=>createApp({origin,password:'short'}),/16/);
});
