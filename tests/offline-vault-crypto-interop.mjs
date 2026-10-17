import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const here=path.dirname(fileURLToPath(import.meta.url));
const root=path.dirname(here);
const b64u=a=>Buffer.from(a).toString('base64url');
const unb64u=s=>Buffer.from(s,'base64url');

const pair=await crypto.webcrypto.subtle.generateKey({name:'ECDH',namedCurve:'P-256'},false,['deriveBits']);
const publicSpki=await crypto.webcrypto.subtle.exportKey('spki',pair.publicKey);
const license=crypto.randomUUID();
const php=`
require ${JSON.stringify(path.join(root,'app','Services','OfflineVault.php'))};
$ref=new ReflectionClass(EpaperSaas\\Services\\OfflineVault::class);
$m=$ref->getMethod('wrapForDevice');
$m->setAccessible(true);
$cek=random_bytes(32);
$wrapped=$m->invoke(null,$cek,$argv[1],$argv[2]);
echo json_encode(['cek'=>rtrim(strtr(base64_encode($cek),'+/','-_'),'='),'wrapped'=>$wrapped],JSON_UNESCAPED_SLASHES);
`;
const raw=execFileSync('php',['-r',php,b64u(publicSpki),license],{encoding:'utf8'});
const data=JSON.parse(raw);
const w=data.wrapped;
const serverPub=await crypto.webcrypto.subtle.importKey('spki',unb64u(w.server_public_spki),{name:'ECDH',namedCurve:'P-256'},false,[]);
const secret=await crypto.webcrypto.subtle.deriveBits({name:'ECDH',public:serverPub},pair.privateKey,256);
const material=await crypto.webcrypto.subtle.importKey('raw',secret,'HKDF',false,['deriveKey']);
const wrapKey=await crypto.webcrypto.subtle.deriveKey({name:'HKDF',hash:'SHA-256',salt:new TextEncoder().encode(w.salt),info:new TextEncoder().encode(w.info)},material,{name:'AES-GCM',length:256},false,['decrypt']);
const combined=Buffer.concat([unb64u(w.ciphertext),unb64u(w.tag)]);
const plain=await crypto.webcrypto.subtle.decrypt({name:'AES-GCM',iv:unb64u(w.iv),additionalData:new TextEncoder().encode(license),tagLength:128},wrapKey,combined);
const ok=Buffer.from(plain).equals(unb64u(data.cek));
console.log(`ECDH_WRAP_MATCH ${ok} ${Buffer.byteLength(Buffer.from(plain))}`);
if(!ok)process.exit(1);
