/* Browser-engine-only Platform GET/POST CSRF diagnostic; no valid credentials or raw token logging. */
const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({headless:true, args:['--no-sandbox']});
  let result={kind:'chromium_synthetic_platform_login', testedAt:new Date().toISOString(),
              host:'platform.digiti.in', realCredentialsUsed:false, realUserTargeted:false};
  try {
    const context=await browser.newContext({ignoreHTTPSErrors:false});
    const page=await context.newPage();
    const login=await page.goto('https://platform.digiti.in/login',{waitUntil:'domcontentloaded',timeout:16000});
    result.getStatus=login?.status()||0;
    result.formHasCSRF=await page.locator('form input[name="_csrf"]').count()>0;
    result.formTokenPopulated=await page.locator('form input[name="_csrf"]').evaluate(el=>el.value.length>=48).catch(()=>false);
    const cookiesBefore=await context.cookies('https://platform.digiti.in');
    const sidBefore=cookiesBefore.find(c=>c.name==='digiti_platform');
    result.cookieBeforePOSTPresent=!!sidBefore;
    result.cookieSecure=!!sidBefore?.secure;
    result.cookieHttpOnly=!!sidBefore?.httpOnly;
    if(result.getStatus===200 && result.formTokenPopulated && sidBefore){
      const impossibleEmail='qa-'+Date.now()+'-'+Math.random().toString(16).slice(2)+'@example.invalid';
      await page.locator('form input[name="email"]').fill(impossibleEmail);
      await page.locator('form input[name="password"]').fill('invalid-test-only-do-not-authenticate');
      const responsePromise=page.waitForResponse(x=>x.url()==='https://platform.digiti.in/login' && x.request().method()==='POST',{timeout:15000});
      await page.locator('form button[type="submit"], form button.primary').first().click({timeout:8000});
      const response=await responsePromise;
      result.postStatus=response.status();
      const body=(await response.text()).slice(0,12000);
      result.CSRFInvalidResponse=body.includes('CSRF_INVALID');
      result.expectedWrongCredentials=body.includes('Invalid email, password or authenticator code');
      const cookiesAfter=await context.cookies('https://platform.digiti.in');
      const sidAfter=cookiesAfter.find(c=>c.name==='digiti_platform');
      result.cookieAfterPOSTPresent=!!sidAfter;
      result.sameSessionBeforeAndAfter=!!sidBefore && !!sidAfter && sidBefore.value===sidAfter.value;
      result.pass=result.postStatus===200 && result.expectedWrongCredentials && result.sameSessionBeforeAndAfter && !result.CSRFInvalidResponse;
    } else { result.pass=false; result.skippedPOST='Login form or session unavailable'; }
    await context.close();
  } catch(e){result.errorType=e.name||'Error';result.pass=false;} 
  finally { await browser.close(); }
  fs.writeFileSync('platform-login-chromium-report.json', JSON.stringify(result,null,2)+'\n');
  for(const [key,value] of Object.entries(result))console.log(key+': '+String(value));
  if(!result.pass) process.exitCode=1;
})().catch(e=>{console.error('Unexpected diagnostic failure',e.name);process.exitCode=1});
