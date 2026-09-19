import { DatabaseSync, backup } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
const dir=process.env.DATA_DIR||'./data';
mkdirSync(join(dir,'backups'),{recursive:true,mode:0o700});
const db=new DatabaseSync(join(dir,'teamvicov.sqlite'),{readOnly:true});
const destination=join(dir,'backups',`teamvicov-${new Date().toISOString().replace(/[:.]/g,'-')}.sqlite`);
await backup(db,destination); db.close(); console.log(destination);
