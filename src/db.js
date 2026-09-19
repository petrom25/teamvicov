import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
export function openDatabase(directory) {
  mkdirSync(directory, { recursive: true, mode: 0o700 });
  const db = new DatabaseSync(join(directory, 'teamvicov.sqlite'));
  db.exec(`PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;
    CREATE TABLE IF NOT EXISTS pigeons (
      id TEXT PRIMARY KEY, ring TEXT NOT NULL UNIQUE COLLATE NOCASE,
      name TEXT NOT NULL DEFAULT '', sex TEXT NOT NULL CHECK(sex IN ('M','F','U')),
      color TEXT NOT NULL DEFAULT '', owner TEXT NOT NULL DEFAULT '',
      results TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '',
      father_id TEXT REFERENCES pigeons(id), mother_id TEXT REFERENCES pigeons(id),
      published INTEGER NOT NULL DEFAULT 0 CHECK(published IN (0,1)),
      image BLOB, image_type TEXT, version INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL, updated_at TEXT NOT NULL
    ) STRICT;
    PRAGMA user_version=1;`);
  return db;
}
export const fields = 'id,ring,name,sex,color,owner,results,notes,father_id,mother_id,published,version,created_at,updated_at,(image IS NOT NULL) AS has_image';
export function validatePigeon(db, data, id) {
  const fail = message => { throw Object.assign(new Error(message), { status: 400 }); };
  const text = (key, max) => {
    if (typeof data[key] !== 'string' || data[key].length > max) fail(`Câmp invalid: ${key}.`);
    return data[key].trim();
  };
  const ring = text('ring', 64).toUpperCase().replace(/\s+/g, ' ');
  if (!ring || !/^[A-Z0-9 -]+$/.test(ring)) fail('Seria poate conține litere, cifre, spații și cratime.');
  const name = text('name', 100), color = text('color', 100), owner = text('owner', 200);
  const results = text('results', 10000), notes = text('notes', 10000);
  if (!['M','F','U'].includes(data.sex)) fail('Selectează sexul porumbelului.');
  if (typeof data.published !== 'boolean') fail('Vizibilitate invalidă.');
  const parents = [data.father_id || null, data.mother_id || null];
  if (parents[0] && parents[0] === parents[1]) fail('Părinții trebuie să fie diferiți.');
  for (const [index, parentId] of parents.entries()) {
    if (!parentId) continue;
    if (typeof parentId !== 'string') fail('Părinte invalid.');
    const parent = db.prepare('SELECT sex FROM pigeons WHERE id=?').get(parentId);
    if (!parent) fail('Părintele selectat nu mai există.');
    if (parent.sex !== 'U' && parent.sex !== (index === 0 ? 'M' : 'F')) fail('Sexul părintelui nu corespunde.');
    const seen = new Set(); const stack = [parentId];
    while (stack.length) {
      const current = stack.pop();
      if (current === id) fail('Legătura ar crea un ciclu în pedigree.');
      if (seen.has(current)) continue;
      seen.add(current);
      const p = db.prepare('SELECT father_id,mother_id FROM pigeons WHERE id=?').get(current);
      if (p?.father_id) stack.push(p.father_id);
      if (p?.mother_id) stack.push(p.mother_id);
    }
  }
  if (id) {
    if (data.sex === 'F' && db.prepare('SELECT 1 FROM pigeons WHERE father_id=?').get(id)) fail('Porumbelul este deja folosit ca tată.');
    if (data.sex === 'M' && db.prepare('SELECT 1 FROM pigeons WHERE mother_id=?').get(id)) fail('Porumbelul este deja folosit ca mamă.');
  }
  return [ring,name,data.sex,color,owner,results,notes,...parents,Number(data.published)];
}
