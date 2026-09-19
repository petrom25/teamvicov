import { createServer } from 'node:http';
import { randomBytes, randomUUID, scrypt, timingSafeEqual } from 'node:crypto';
import { promisify } from 'node:util';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';
import { openDatabase, fields, validatePigeon } from './db.js';
const derive = promisify(scrypt);
const error = (status, message) => Object.assign(new Error(message), { status });
export async function createApp(config) {
  const origin = new URL(config.origin).origin;
  const secure = origin.startsWith('https:');
  if (config.production && !secure) throw new Error('APP_ORIGIN trebuie să înceapă cu https:// în producție.');
  if (!config.password || config.password.length < 16) throw new Error('ADMIN_PASSWORD trebuie să aibă minimum 16 caractere.');
  const salt = randomBytes(32), expected = await derive(config.password, salt, 64);
  const db = openDatabase(config.dataDir);
  const sessions = new Map();
  let loginAttempts = [], pendingLogins = 0;
  const assets = new Map([
    ['/', ['index.html','text/html; charset=utf-8']], ['/admin', ['index.html','text/html; charset=utf-8']],
    ['/app.js', ['app.js','text/javascript; charset=utf-8']], ['/style.css', ['style.css','text/css; charset=utf-8']],
    ['/favicon.svg', ['favicon.svg','image/svg+xml']]
  ].map(([url,[file,type]]) => [url, {type,body:readFileSync(new URL(`../public/${file}`,import.meta.url))}]));
  const clean = setInterval(() => {
    for (const [key, value] of sessions) if (value.expires < Date.now()) sessions.delete(key);
  }, 60000).unref();
  function session(req) {
    const token = /(?:^|;\s*)tv_session=([a-f0-9]{64})(?:;|$)/.exec(req.headers.cookie || '')?.[1];
    const found = sessions.get(token);
    return found && found.expires > Date.now() ? {token,...found} : null;
  }
  function json(res, status, value) { res.writeHead(status, {'Content-Type':'application/json; charset=utf-8'}); res.end(JSON.stringify(value)); }
  async function body(req, limit=40000) {
    if (!req.headers['content-type']?.startsWith('application/json')) throw error(415,'Format JSON necesar.');
    if (Number(req.headers['content-length']) > limit) throw error(413,'Fișier prea mare.');
    let size=0; const chunks=[];
    for await (const chunk of req) { size+=chunk.length; if(size>limit) throw error(413,'Fișier prea mare.'); chunks.push(chunk); }
    try { const value=JSON.parse(Buffer.concat(chunks).toString()); if (!value || Array.isArray(value) || typeof value!=='object') throw 0; return value; }
    catch { throw error(400,'Date JSON invalide.'); }
  }
  function visible(row, admin) {
    if(admin) return row;
    const {notes,owner,created_at,updated_at,version,...publicRow}=row;
    for(const key of ['father_id','mother_id']) {
      if(publicRow[key] && !db.prepare('SELECT 1 FROM pigeons WHERE id=? AND published=1').get(publicRow[key])) publicRow[key]=null;
    }
    return publicRow;
  }
  const server=createServer(async (req,res) => {
    res.setHeader('X-Content-Type-Options','nosniff');
    res.setHeader('Referrer-Policy','same-origin');
    res.setHeader('X-Frame-Options','DENY');
    res.setHeader('Cache-Control','no-store');
    res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    if(secure) res.setHeader('Strict-Transport-Security','max-age=31536000');
    try {
      const url=new URL(req.url,origin), path=url.pathname, method=req.method;
      const user=session(req);
      if (!['GET','HEAD'].includes(method) && req.headers.origin !== origin) throw error(403,'Originea cererii nu este autorizată.');
      if(path==='/healthz' && method==='GET') { db.prepare('SELECT 1').get(); return json(res,200,{status:'ok',version:'0.1.0'}); }
      if(assets.has(path) && ['GET','HEAD'].includes(method)) {
        const asset=assets.get(path); res.writeHead(200,{'Content-Type':asset.type}); return res.end(method==='HEAD'?undefined:asset.body);
      }
      if(path==='/api/session' && method==='GET') return json(res,200,{authenticated:!!user});
      if(path==='/api/login' && method==='POST') {
        loginAttempts=loginAttempts.filter(t=>t>Date.now()-600000);
        // One administrator: global limiter also covers changing spoofed IP headers.
        if(loginAttempts.length>=20 || pendingLogins>=3) throw error(429,'Prea multe încercări. Reîncearcă peste 10 minute.');
        const data=await body(req,4096);
        loginAttempts.push(Date.now()); pendingLogins++;
        let valid=false;
        try { const actual=await derive(typeof data.password==='string'?data.password:'',salt,64); valid=timingSafeEqual(actual,expected); }
        finally {pendingLogins--;}
        if(!valid) throw error(401,'Parolă incorectă.');
        if(sessions.size>=100) sessions.delete(sessions.keys().next().value);
        const token=randomBytes(32).toString('hex'); sessions.set(token,{expires:Date.now()+8*3600000});
        res.setHeader('Set-Cookie',`tv_session=${token}; Path=/; HttpOnly; SameSite=Strict; Max-Age=28800${secure?'; Secure':''}`);
        return json(res,200,{ok:true});
      }
      if(path==='/api/logout' && method==='POST') {
        if(user) sessions.delete(user.token);
        res.setHeader('Set-Cookie',`tv_session=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0${secure?'; Secure':''}`);
        return json(res,200,{ok:true});
      }
      const admin=path.startsWith('/api/admin/');
      if(admin && !user) throw error(401,'Autentificarea este necesară.');
      if((path==='/api/pigeons' || path==='/api/admin/pigeons') && method==='GET') {
        const rows=db.prepare(`SELECT ${fields} FROM pigeons ${admin?'':'WHERE published=1'} ORDER BY created_at DESC`).all();
        return json(res,200,rows.map(row=>visible(row,admin)));
      }
      const photo=/^\/api\/(admin\/)?pigeons\/([a-f0-9-]{36})\/image$/.exec(path);
      if(photo && method==='GET') {
        const row=db.prepare(`SELECT image,image_type FROM pigeons WHERE id=? ${admin?'':'AND published=1'}`).get(photo[2]);
        if(!row?.image) throw error(404,'Fotografia nu există.');
        res.writeHead(200,{'Content-Type':row.image_type}); return res.end(Buffer.from(row.image));
      }
      if(photo && admin && method==='PUT') {
        const data=await body(req,7500000);
        const row=db.prepare('SELECT version FROM pigeons WHERE id=?').get(photo[2]);
        if(!row) throw error(404,'Porumbelul nu există.');
        if(row.version!==data.version) throw error(409,'Fișa a fost modificată. Redeschide-o înainte de salvare.');
        let buffer=null,type=null;
        if(data.image!==null) {
          const match=typeof data.image==='string' && /^data:(image\/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$/.exec(data.image);
          if(!match) throw error(400,'Folosește o fotografie JPG, PNG sau WebP.');
          type=match[1]; buffer=Buffer.from(match[2],'base64');
          if(buffer.length>5*1024*1024 || buffer.length<12) throw error(400,'Fotografia trebuie să aibă cel mult 5 MB.');
          const valid=type==='image/jpeg'?buffer.subarray(0,3).equals(Buffer.from([255,216,255])):type==='image/png'?buffer.subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10])):buffer.toString('ascii',0,4)==='RIFF'&&buffer.toString('ascii',8,12)==='WEBP';
          if(!valid) throw error(400,'Conținutul fotografiei nu corespunde formatului.');
        }
        db.prepare('UPDATE pigeons SET image=?,image_type=?,version=version+1,updated_at=? WHERE id=?').run(buffer,type,new Date().toISOString(),photo[2]);
        return json(res,200,{ok:true,version:row.version+1});
      }
      const match=/^\/api\/admin\/pigeons(?:\/([a-f0-9-]{36}))?$/.exec(path);
      if(match && (method==='POST'&&!match[1] || method==='PUT'&&match[1])) {
        const data=await body(req), id=match[1]||randomUUID();
        const values=validatePigeon(db,data,id), now=new Date().toISOString();
        if(method==='POST') db.prepare('INSERT INTO pigeons (ring,name,sex,color,owner,results,notes,father_id,mother_id,published,id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)').run(...values,id,now,now);
        else {
          const current=db.prepare('SELECT version FROM pigeons WHERE id=?').get(id);
          if(!current) throw error(404,'Porumbelul nu există.');
          if(current.version!==data.version) throw error(409,'Fișa a fost modificată. Redeschide-o înainte de salvare.');
          db.prepare('UPDATE pigeons SET ring=?,name=?,sex=?,color=?,owner=?,results=?,notes=?,father_id=?,mother_id=?,published=?,version=version+1,updated_at=? WHERE id=?').run(...values,now,id);
        }
        return json(res,method==='POST'?201:200,db.prepare(`SELECT ${fields} FROM pigeons WHERE id=?`).get(id));
      }
      throw error(404,'Pagina nu există.');
    } catch(e) {
      const duplicate=e.message?.includes('UNIQUE constraint failed: pigeons.ring');
      const status=duplicate?409:e.status||500;
      if(status===500) console.error('Request failed:',e.code||e.name);
      if(!res.headersSent) json(res,status,{error:duplicate?'Seria există deja în registru.':status===500?'Eroare internă. Încearcă din nou.':e.message});
      else res.end();
    }
  });
  server.requestTimeout=15000; server.headersTimeout=10000;
  server.on('close',()=>{clearInterval(clean);db.close();});
  return server;
}
if(process.argv[1] && resolve(process.argv[1])===fileURLToPath(import.meta.url)) {
  const server=await createApp({origin:process.env.APP_ORIGIN||'http://localhost:3000',password:process.env.ADMIN_PASSWORD,dataDir:process.env.DATA_DIR||'./data',production:process.env.NODE_ENV==='production'});
  server.listen(Number(process.env.PORT||3000),'0.0.0.0',()=>console.log('Team Vicov listening on port',process.env.PORT||3000));
  for(const signal of ['SIGTERM','SIGINT']) process.on(signal,()=>{server.close();setTimeout(()=>process.exit(1),10000).unref();});
}
