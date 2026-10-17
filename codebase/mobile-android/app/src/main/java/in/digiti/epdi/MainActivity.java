package in.digiti.epdi;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.net.Uri;
import android.net.http.SslError;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.os.Bundle;
import android.os.Build;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import com.google.firebase.messaging.FirebaseMessaging;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.security.KeyStore;

import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.UUID;
import android.util.Base64;

public final class MainActivity extends Activity {
    private static final String BASE_URL = "https://epaper.digiti.in";
    private static final String HOME_URL = BASE_URL + "/";
    private static final String HOST = "epaper.digiti.in";

    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private final SessionClient session = new SessionClient();
    private FrameLayout root;
    private LinearLayout authPanel;
    private WebView webView;
    private ProgressBar progress;
    private TextView status;
    private EditText phoneInput;
    private EditText codeInput;
    private String csrf = "";
    private String challengeGuid = "";
    private final List<String> channels = new ArrayList<>();
    private ConnectivityManager.NetworkCallback networkCallback;
    private boolean deviceFallback = false;
    private String activeDesktopChallenge = "";

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        root = new FrameLayout(this);
        setContentView(root);
        // Keep native API requests and the embedded WebView on the same EP-DI PHP session.
        String existingCookies = CookieManager.getInstance().getCookie(BASE_URL);
        if (existingCookies != null && !existingCookies.trim().isEmpty()) session.importCookieHeader(existingCookies);
        showLoading("Preparing secure sign in…");
        requestNotificationPermission();
        refreshFcmToken();
        bootstrap();
        handleIntent(getIntent());
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent); setIntent(intent); handleIntent(intent);
    }

    @Override protected void onResume() {
        super.onResume();
        if (getTrustedDeviceGuid().length() > 0) checkPendingDesktopLogin();
    }

    private void handleIntent(Intent intent) {
        if (intent != null && intent.hasExtra("desktop_challenge")) checkPendingDesktopLogin();
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, 20108);
        }
    }

    private void bootstrap() {
        executor.execute(() -> {
            try {
                ApiResponse r = session.request("GET", BASE_URL + "/api/v1/auth/mobile/bootstrap", null, null);
                JSONObject data = new JSONObject(r.body);
                if (!data.optBoolean("ok", false)) throw new Exception("Unable to initialize mobile sign in.");
                csrf = data.optString("csrf", "");
                if (data.optBoolean("authenticated", false)) {
                    if ("LOCAL_MOBILE".equalsIgnoreCase(data.optString("auth_source", ""))) {
                        try { registerTrustedDeviceSync(); } catch (Exception ignored) { }
                    }
                    runOnUiThread(this::openReader);
                    return;
                }
                channels.clear();
                JSONArray a = data.optJSONArray("channels");
                if (a != null) for (int i = 0; i < a.length(); i++) channels.add(a.optString(i).toLowerCase(Locale.ROOT));
                boolean enabled = data.optBoolean("enabled", false);
                deviceFallback = data.optBoolean("device_fallback", false);
                String cc = data.optString("default_country_code", "+91");
                if (channels.isEmpty() && deviceFallback) {
                    runOnUiThread(this::createTemporaryDeviceAccount);
                } else {
                    runOnUiThread(() -> showPhoneScreen(enabled, cc));
                }
            } catch (Exception e) {
                runOnUiThread(() -> showRecoverableError("Could not reach EP-DI. " + safeMessage(e), this::bootstrap));
            }
        });
    }

    private void showLoading(String message) {
        root.removeAllViews();
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setGravity(Gravity.CENTER);
        box.setPadding(dp(28), dp(28), dp(28), dp(28));
        progress = new ProgressBar(this);
        box.addView(progress, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        status = text(message, 16, Color.DKGRAY);
        LinearLayout.LayoutParams sp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        sp.topMargin = dp(16);
        box.addView(status, sp);
        root.addView(box, match());
    }

    private String getInstallationId() {
        SharedPreferences prefs = getSharedPreferences("epdi_installation", MODE_PRIVATE);
        String id = prefs.getString("installation_id", "");
        if (id == null || id.trim().isEmpty()) {
            id = UUID.randomUUID().toString();
            prefs.edit().putString("installation_id", id).apply();
        }
        return id;
    }

    private String getDeviceSecret() {
        SharedPreferences prefs=getSharedPreferences("epdi_trusted",MODE_PRIVATE);
        String encrypted=prefs.getString("device_secret_cipher","");
        if(encrypted!=null&&!encrypted.isEmpty()){
            try{return decryptTrustedSecret(encrypted);}catch(Exception ignored){}
        }
        String legacy=prefs.getString("device_secret","");
        String secret=legacy==null?"":legacy;
        if(secret.isEmpty()){
            byte[] bytes=new byte[32];new SecureRandom().nextBytes(bytes);
            secret=Base64.encodeToString(bytes,Base64.URL_SAFE|Base64.NO_WRAP|Base64.NO_PADDING);
        }
        try{
            String cipher=encryptTrustedSecret(secret);
            prefs.edit().putString("device_secret_cipher",cipher).remove("device_secret").apply();
        }catch(Exception e){prefs.edit().putString("device_secret",secret).apply();}
        return secret;
    }

    private SecretKey trustedStorageKey() throws Exception {
        final String alias="epdi_trusted_device_v1";
        KeyStore ks=KeyStore.getInstance("AndroidKeyStore");ks.load(null);
        java.security.Key key=ks.getKey(alias,null);if(key instanceof SecretKey)return(SecretKey)key;
        KeyGenerator kg=KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES,"AndroidKeyStore");
        kg.init(new KeyGenParameterSpec.Builder(alias,KeyProperties.PURPOSE_ENCRYPT|KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build());
        return kg.generateKey();
    }
    private String encryptTrustedSecret(String secret) throws Exception {
        Cipher c=Cipher.getInstance("AES/GCM/NoPadding");c.init(Cipher.ENCRYPT_MODE,trustedStorageKey());byte[] enc=c.doFinal(secret.getBytes(StandardCharsets.UTF_8));byte[] iv=c.getIV();byte[] out=new byte[iv.length+enc.length];System.arraycopy(iv,0,out,0,iv.length);System.arraycopy(enc,0,out,iv.length,enc.length);return Base64.encodeToString(out,Base64.NO_WRAP);
    }
    private String decryptTrustedSecret(String encoded) throws Exception {
        byte[] raw=Base64.decode(encoded,Base64.NO_WRAP);if(raw.length<13)throw new Exception("Invalid trusted secret");byte[] iv=new byte[12];byte[] enc=new byte[raw.length-12];System.arraycopy(raw,0,iv,0,12);System.arraycopy(raw,12,enc,0,enc.length);Cipher c=Cipher.getInstance("AES/GCM/NoPadding");c.init(Cipher.DECRYPT_MODE,trustedStorageKey(),new GCMParameterSpec(128,iv));return new String(c.doFinal(enc),StandardCharsets.UTF_8);
    }

    private String getTrustedDeviceGuid(){return getSharedPreferences("epdi_trusted",MODE_PRIVATE).getString("device_guid","");}
    private String getCachedPushToken(){return getSharedPreferences("epdi_trusted",MODE_PRIVATE).getString("push_token","");}
    private void setTrustedDeviceGuid(String guid){if(guid!=null&&!guid.isEmpty())getSharedPreferences("epdi_trusted",MODE_PRIVATE).edit().putString("device_guid",guid).apply();}

    private void refreshFcmToken() {
        if(!EpdiApplication.firebaseReady())return;
        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
            if(!task.isSuccessful()||task.getResult()==null)return;
            String token=task.getResult();getSharedPreferences("epdi_trusted",MODE_PRIVATE).edit().putString("push_token",token).apply();
            if(!getTrustedDeviceGuid().isEmpty())executor.execute(() -> { try { updatePushTokenSync(token); } catch(Exception ignored){} });
        });
    }

    private JSONObject registerTrustedDeviceSync() throws Exception {
        JSONObject body=new JSONObject()
                .put("installation_id",getInstallationId()).put("device_secret",getDeviceSecret())
                .put("push_token",getCachedPushToken()).put("device_label",Build.MANUFACTURER+" "+Build.MODEL);
        ApiResponse r=session.request("POST",BASE_URL+"/api/v1/auth/trusted/register",body.toString(),csrf);
        JSONObject data=new JSONObject(r.body);if(!data.optBoolean("ok",false))throw new Exception(data.optString("message","Trusted app registration failed."));
        setTrustedDeviceGuid(data.optString("device_guid",""));return data;
    }

    private void updatePushTokenSync(String token) throws Exception {
        String guid=getTrustedDeviceGuid();if(guid.isEmpty()||token==null||token.isEmpty())return;
        JSONObject body=new JSONObject().put("device_guid",guid).put("device_secret",getDeviceSecret()).put("push_token",token);
        session.request("POST",BASE_URL+"/api/v1/auth/trusted/push-token",body.toString(),null);
    }

    private void checkPendingDesktopLogin() {
        String guid=getTrustedDeviceGuid();if(guid.isEmpty())return;
        executor.execute(() -> {
            try {
                JSONObject body=new JSONObject().put("device_guid",guid).put("device_secret",getDeviceSecret());
                ApiResponse r=session.request("POST",BASE_URL+"/api/v1/auth/trusted/pending",body.toString(),null);
                JSONObject data=new JSONObject(r.body);JSONObject c=data.optJSONObject("challenge");if(!data.optBoolean("ok",false)||c==null)return;
                String challenge=c.optString("challenge_guid","");if(challenge.isEmpty()||challenge.equals(activeDesktopChallenge))return;
                activeDesktopChallenge=challenge;String code=c.optString("display_code","");String client=c.optString("client_summary","Desktop browser");
                runOnUiThread(() -> showDesktopApprovalDialog(challenge,code,client));
            } catch(Exception ignored) { }
        });
    }

    private void showDesktopApprovalDialog(String challenge,String code,String client) {
        if(isFinishing())return;
        new AlertDialog.Builder(this).setTitle("Desktop login request")
                .setMessage(client+"\n\nMatching code: "+code+"\n\nApprove only if this code is also visible on your desktop.")
                .setPositiveButton("Approve",(d,w)->decideDesktopLogin(challenge,"approve"))
                .setNegativeButton("Deny",(d,w)->decideDesktopLogin(challenge,"deny"))
                .setOnCancelListener(d -> activeDesktopChallenge="").show();
    }

    private void decideDesktopLogin(String challenge,String decision) {
        executor.execute(() -> {
            try {
                JSONObject body=new JSONObject().put("device_guid",getTrustedDeviceGuid()).put("device_secret",getDeviceSecret()).put("challenge_guid",challenge).put("decision",decision);
                ApiResponse r=session.request("POST",BASE_URL+"/api/v1/auth/trusted/decision",body.toString(),null);JSONObject data=new JSONObject(r.body);
                if(!data.optBoolean("ok",false))throw new Exception("Unable to update desktop login request.");
                getSharedPreferences("epdi_trusted",MODE_PRIVATE).edit().remove("pending_challenge").remove("pending_code").remove("pending_client").apply();
                activeDesktopChallenge="";runOnUiThread(() -> Toast.makeText(this,"approve".equals(decision)?"Desktop login approved":"Desktop login denied",Toast.LENGTH_SHORT).show());
            } catch(Exception e) { activeDesktopChallenge="";runOnUiThread(() -> Toast.makeText(this,"Could not update desktop login",Toast.LENGTH_SHORT).show()); }
        });
    }

    private void createTemporaryDeviceAccount() {
        showLoading("Creating your temporary reader…");
        executor.execute(() -> {
            try {
                JSONObject body = new JSONObject().put("installation_id", getInstallationId());
                ApiResponse r = session.request("POST", BASE_URL + "/api/v1/auth/device", body.toString(), csrf);
                JSONObject data = new JSONObject(r.body);
                if (!data.optBoolean("ok", false)) throw new Exception(data.optString("message", "Temporary reader access is unavailable."));
                session.installCookiesInWebView(BASE_URL);
                runOnUiThread(this::openReader);
            } catch (Exception e) {
                runOnUiThread(() -> showRecoverableError("Could not create temporary reader access. " + safeMessage(e), this::bootstrap));
            }
        });
    }

    private void showPhoneScreen(boolean enabled, String defaultCountryCode) {
        root.removeAllViews();
        authPanel = new LinearLayout(this);
        authPanel.setOrientation(LinearLayout.VERTICAL);
        authPanel.setGravity(Gravity.CENTER_HORIZONTAL);
        authPanel.setPadding(dp(28), dp(48), dp(28), dp(28));

        TextView brand = text("EP-DI", 16, Color.DKGRAY);
        authPanel.addView(brand, wrap());
        TextView title = text(enabled ? "Continue with mobile" : "Open EP-DI", 28, Color.BLACK);
        LinearLayout.LayoutParams titleParams = wrap(); titleParams.topMargin = dp(14); authPanel.addView(title, titleParams);
        TextView intro = text(enabled ? "Verify once. New numbers create a local reader account automatically." : "Mobile sign in has not been enabled on the server yet.", 16, Color.DKGRAY);
        intro.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams introParams = wrap(); introParams.topMargin = dp(10); authPanel.addView(intro, introParams);

        if (enabled) {
            phoneInput = new EditText(this);
            phoneInput.setHint(defaultCountryCode + " 98765 43210");
            phoneInput.setInputType(InputType.TYPE_CLASS_PHONE);
            phoneInput.setSingleLine(true);
            LinearLayout.LayoutParams field = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(58)); field.topMargin = dp(28); authPanel.addView(phoneInput, field);

            if (channels.contains("sna")) authPanel.addView(actionButton("Verify automatically", () -> startAuth("sna")), buttonParams());
            if (channels.contains("whatsapp")) authPanel.addView(actionButton("Continue with WhatsApp", () -> startAuth("whatsapp")), buttonParams());
            if (channels.contains("sms")) authPanel.addView(actionButton("Send code by SMS", () -> startAuth("sms")), buttonParams());
        }

        authPanel.addView(actionButton(enabled ? "Read without signing in" : "Open ePaper", this::openReader), buttonParams());
        authPanel.addView(actionButton("Publisher / admin sign in", () -> openReaderAt(BASE_URL + "/login")), buttonParams());
        root.addView(authPanel, match());
    }

    private void startAuth(String channel) {
        if (phoneInput == null) return;
        String phone = phoneInput.getText().toString().trim();
        if (phone.isEmpty()) { phoneInput.setError("Enter your mobile number"); return; }
        showLoading(channel.equals("sna") ? "Checking your mobile network…" : "Sending verification…");
        executor.execute(() -> {
            try {
                JSONObject body = new JSONObject().put("phone", phone).put("channel", channel).put("installation_id", getInstallationId());
                ApiResponse r = session.request("POST", BASE_URL + "/api/v1/auth/mobile/start", body.toString(), csrf);
                JSONObject data = new JSONObject(r.body);
                if (!data.optBoolean("ok", false)) throw new Exception(data.optString("message", "Unable to start verification."));
                challengeGuid = data.optString("challenge_guid", "");
                String actual = data.optString("channel", channel).toLowerCase(Locale.ROOT);
                String masked = data.optString("phone_masked", phone);
                String snaUrl = data.optString("sna_url", "");
                if (actual.equals("sna") && !snaUrl.isEmpty()) {
                    runOnUiThread(() -> invokeSna(snaUrl));
                } else {
                    runOnUiThread(() -> showCodeScreen(masked, actual));
                }
            } catch (Exception e) {
                runOnUiThread(() -> showAuthError(safeMessage(e)));
            }
        });
    }

    private void invokeSna(String snaUrl) {
        status.setText("Verifying through your SIM/mobile network…");
        ConnectivityManager cm = (ConnectivityManager)getSystemService(Context.CONNECTIVITY_SERVICE);
        NetworkRequest request = new NetworkRequest.Builder()
                .addTransportType(NetworkCapabilities.TRANSPORT_CELLULAR)
                .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                .build();
        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override public void onAvailable(Network network) {
                executor.execute(() -> {
                    try {
                        HttpURLConnection c = (HttpURLConnection)network.openConnection(new URL(snaUrl));
                        c.setRequestMethod("POST"); c.setInstanceFollowRedirects(true); c.setConnectTimeout(8000); c.setReadTimeout(10000); c.setDoOutput(true);
                        c.getOutputStream().write(new byte[0]);
                        int statusCode = c.getResponseCode();
                        readAll(statusCode >= 400 ? c.getErrorStream() : c.getInputStream());
                        c.disconnect();
                        try { cm.unregisterNetworkCallback(this); } catch (Exception ignored) {}
                        if (statusCode < 200 || statusCode >= 400) throw new Exception("Carrier verification was not available.");
                        runOnUiThread(() -> verifyCode(""));
                    } catch (Exception e) {
                        try { cm.unregisterNetworkCallback(this); } catch (Exception ignored) {}
                        runOnUiThread(() -> showAuthError("Automatic verification was unavailable. Choose WhatsApp or SMS. " + safeMessage(e)));
                    }
                });
            }
            @Override public void onUnavailable() {
                runOnUiThread(() -> showAuthError("Mobile data was unavailable for automatic verification. Choose WhatsApp or SMS."));
            }
        };
        try {
            if (Build.VERSION.SDK_INT >= 26) cm.requestNetwork(request, networkCallback, 10000);
            else cm.requestNetwork(request, networkCallback);
        } catch (Exception e) { showAuthError("Automatic verification is unavailable on this device. Choose WhatsApp or SMS."); }
    }

    private void showCodeScreen(String masked, String channel) {
        root.removeAllViews();
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL); box.setGravity(Gravity.CENTER_HORIZONTAL); box.setPadding(dp(28), dp(52), dp(28), dp(28));
        box.addView(text("Verify mobile", 28, Color.BLACK), wrap());
        TextView hint = text("Enter the code sent to " + masked + " by " + channel.toUpperCase(Locale.ROOT) + ".", 16, Color.DKGRAY); hint.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams hp=wrap(); hp.topMargin=dp(12); box.addView(hint,hp);
        codeInput = new EditText(this); codeInput.setHint("Verification code"); codeInput.setInputType(InputType.TYPE_CLASS_NUMBER); codeInput.setSingleLine(true);
        LinearLayout.LayoutParams fp=new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(58)); fp.topMargin=dp(26); box.addView(codeInput,fp);
        box.addView(actionButton("Verify & continue", () -> verifyCode(codeInput.getText().toString())), buttonParams());
        box.addView(actionButton("Use another number", () -> bootstrap()), buttonParams());
        root.addView(box, match()); codeInput.requestFocus();
    }

    private void verifyCode(String code) {
        showLoading("Signing you in…");
        executor.execute(() -> {
            try {
                boolean silent = code == null || code.trim().isEmpty();
                int maxChecks = silent ? 4 : 1;
                for (int check = 0; check < maxChecks; check++) {
                    JSONObject body = new JSONObject().put("challenge_guid", challengeGuid).put("installation_id", getInstallationId())
                            .put("device_secret",getDeviceSecret()).put("push_token",getCachedPushToken())
                            .put("device_label",Build.MANUFACTURER+" "+Build.MODEL);
                    if (!silent) body.put("code", code.trim());
                    ApiResponse r = session.request("POST", BASE_URL + "/api/v1/auth/mobile/verify", body.toString(), csrf);
                    JSONObject data = new JSONObject(r.body);
                    if (data.optBoolean("ok", false)) {
                        JSONObject trusted=data.optJSONObject("trusted_device");if(trusted!=null)setTrustedDeviceGuid(trusted.optString("device_guid",""));
                        session.installCookiesInWebView(BASE_URL);
                        runOnUiThread(this::openReader);
                        return;
                    }
                    String errorCode = data.optString("code", "");
                    if (silent && "MOBILE_AUTH_PENDING".equals(errorCode) && check < maxChecks - 1) {
                        long retryMs = Math.max(500L, data.optLong("retry_after_ms", 900L)) + (check * 450L);
                        runOnUiThread(() -> { if (status != null) status.setText("Finishing automatic verification…"); });
                        Thread.sleep(retryMs);
                        continue;
                    }
                    throw new Exception(data.optString("message", silent ? "Automatic verification was unavailable. Choose WhatsApp or SMS." : "Verification failed."));
                }
                throw new Exception("Automatic verification is taking longer than expected. Choose WhatsApp or SMS.");
            } catch (Exception e) {
                runOnUiThread(() -> showAuthError(safeMessage(e)));
            }
        });
    }

    private void showAuthError(String message) {
        root.removeAllViews();
        LinearLayout box = new LinearLayout(this); box.setOrientation(LinearLayout.VERTICAL); box.setGravity(Gravity.CENTER); box.setPadding(dp(28),dp(28),dp(28),dp(28));
        TextView msg=text(message,16,Color.DKGRAY); msg.setGravity(Gravity.CENTER); box.addView(msg,wrap());
        if (phoneInput != null && channels.contains("whatsapp")) box.addView(actionButton("Use WhatsApp instead", () -> startAuth("whatsapp")), buttonParams());
        if (phoneInput != null && channels.contains("sms")) box.addView(actionButton("Send SMS instead", () -> startAuth("sms")), buttonParams());
        box.addView(actionButton("Try again", this::bootstrap), buttonParams());
        box.addView(actionButton("Open ePaper", this::openReader), buttonParams());
        root.addView(box,match());
    }

    private void showRecoverableError(String message, Runnable retry) {
        root.removeAllViews();
        LinearLayout box = new LinearLayout(this); box.setOrientation(LinearLayout.VERTICAL); box.setGravity(Gravity.CENTER); box.setPadding(dp(28),dp(28),dp(28),dp(28));
        TextView msg=text(message,16,Color.DKGRAY); msg.setGravity(Gravity.CENTER); box.addView(msg,wrap());
        box.addView(actionButton("Retry", retry), buttonParams());
        box.addView(actionButton("Open in browser", () -> openExternal(Uri.parse(HOME_URL))), buttonParams());
        root.addView(box,match());
    }

    private void openReader() { openReaderAt(HOME_URL); }

    private void openReaderAt(String url) {
        root.removeAllViews();
        FrameLayout shell = new FrameLayout(this);
        progress = new ProgressBar(this);
        FrameLayout.LayoutParams pp = new FrameLayout.LayoutParams(dp(40), dp(40)); pp.gravity=Gravity.CENTER; shell.addView(progress,pp);
        try {
            webView = new WebView(this);
            WebSettings s = webView.getSettings();
            s.setJavaScriptEnabled(true); s.setDomStorageEnabled(true); s.setDatabaseEnabled(true); s.setAllowFileAccess(false); s.setAllowContentAccess(false); s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
            s.setUserAgentString(s.getUserAgentString() + " EPDI-Android/1.0");
            CookieManager.getInstance().setAcceptCookie(true); CookieManager.getInstance().setAcceptThirdPartyCookies(webView,false);
            webView.setWebViewClient(new WebViewClient() {
                @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                    Uri uri=request.getUrl(); String scheme=uri.getScheme(); String host=uri.getHost();
                    if ("https".equalsIgnoreCase(scheme) && HOST.equalsIgnoreCase(host)) return false;
                    openExternal(uri); return true;
                }
                @Override public void onPageStarted(WebView view,String u,android.graphics.Bitmap favicon){ progress.setVisibility(View.VISIBLE); }
                @Override public void onPageFinished(WebView view,String u){ progress.setVisibility(View.GONE); }
                @Override public void onReceivedSslError(WebView view,SslErrorHandler handler,SslError error){ handler.cancel(); showRecoverableError("Secure connection validation failed.", MainActivity.this::openReader); }
                @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error){ if(request.isForMainFrame()) showRecoverableError("EP-DI could not be loaded inside the app.", MainActivity.this::openReader); }
            });
            shell.addView(webView,0,match()); root.addView(shell,match()); webView.loadUrl(url);
        } catch (Throwable t) {
            showRecoverableError("Android System WebView is unavailable. Update Chrome/System WebView and retry.", this::openReader);
        }
    }

    private void openExternal(Uri uri) {
        try { startActivity(new Intent(Intent.ACTION_VIEW, uri)); }
        catch (ActivityNotFoundException ignored) { }
    }

    private Button actionButton(String label, Runnable action) {
        Button b = new Button(this); b.setText(label); b.setAllCaps(false); b.setTextSize(16); b.setOnClickListener(v -> action.run()); return b;
    }
    private TextView text(String value, int sp, int color) { TextView t=new TextView(this); t.setText(value); t.setTextSize(sp); t.setTextColor(color); return t; }
    private int dp(int v){ return Math.round(v*getResources().getDisplayMetrics().density); }
    private FrameLayout.LayoutParams match(){ return new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,ViewGroup.LayoutParams.MATCH_PARENT); }
    private LinearLayout.LayoutParams wrap(){ return new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT,ViewGroup.LayoutParams.WRAP_CONTENT); }
    private LinearLayout.LayoutParams buttonParams(){ LinearLayout.LayoutParams p=new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,dp(54)); p.topMargin=dp(12); return p; }
    private static String safeMessage(Exception e){ String m=e.getMessage(); return m==null||m.trim().isEmpty()?"Please try again.":m; }

    @Override public void onBackPressed() {
        if (webView != null && webView.canGoBack()) webView.goBack(); else super.onBackPressed();
    }

    @Override protected void onDestroy() {
        if (networkCallback != null) {
            try { ((ConnectivityManager)getSystemService(Context.CONNECTIVITY_SERVICE)).unregisterNetworkCallback(networkCallback); } catch (Exception ignored) {}
        }
        if (webView != null) { webView.stopLoading(); webView.setWebViewClient(null); webView.destroy(); webView=null; }
        executor.shutdownNow(); super.onDestroy();
    }

    private static String readAll(InputStream stream) throws Exception {
        if (stream == null) return "";
        try (BufferedReader br = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder(); String line; while ((line=br.readLine())!=null) sb.append(line); return sb.toString();
        }
    }

    private static final class ApiResponse {
        final int status; final String body; ApiResponse(int status,String body){this.status=status;this.body=body;}
    }

    private static final class SessionClient {
        private final Map<String,String> cookies = new HashMap<>();

        synchronized ApiResponse request(String method, String url, String json, String csrf) throws Exception {
            HttpURLConnection c=(HttpURLConnection)new URL(url).openConnection();
            c.setRequestMethod(method); c.setConnectTimeout(8000); c.setReadTimeout(15000); c.setInstanceFollowRedirects(false); c.setRequestProperty("Accept","application/json"); c.setRequestProperty("User-Agent","EPDI-Android/2.0.2.0");
            if (!cookies.isEmpty()) c.setRequestProperty("Cookie", cookieHeader());
            if (csrf != null && !csrf.isEmpty()) c.setRequestProperty("X-CSRF-Token",csrf);
            if (json != null) {
                c.setDoOutput(true); c.setRequestProperty("Content-Type","application/json; charset=utf-8"); c.getOutputStream().write(json.getBytes(StandardCharsets.UTF_8));
            }
            int status=c.getResponseCode(); captureCookies(c);
            String body=readAll(status>=400?c.getErrorStream():c.getInputStream()); c.disconnect();
            if (status>=300 && status<400) throw new Exception("Unexpected server redirect.");
            return new ApiResponse(status,body);
        }

        private void captureCookies(HttpURLConnection c) {
            for (Map.Entry<String,List<String>> e : c.getHeaderFields().entrySet()) {
                if (e.getKey()==null || !"set-cookie".equalsIgnoreCase(e.getKey())) continue;
                for (String raw : e.getValue()) {
                    if (raw==null) continue; String pair=raw.split(";",2)[0]; int eq=pair.indexOf('='); if(eq>0) cookies.put(pair.substring(0,eq).trim(),pair.substring(eq+1).trim());
                }
            }
        }
        private String cookieHeader(){ StringBuilder b=new StringBuilder(); for(Map.Entry<String,String> e:cookies.entrySet()){ if(b.length()>0)b.append("; "); b.append(e.getKey()).append('=').append(e.getValue()); } return b.toString(); }
        synchronized void importCookieHeader(String header) {
            if (header == null) return;
            for (String pair : header.split(";")) {
                int eq=pair.indexOf('=');
                if(eq>0) cookies.put(pair.substring(0,eq).trim(),pair.substring(eq+1).trim());
            }
        }
        void installCookiesInWebView(String origin) {
            CookieManager cm=CookieManager.getInstance(); cm.setAcceptCookie(true);
            synchronized(this){ for(Map.Entry<String,String> e:cookies.entrySet()) cm.setCookie(origin,e.getKey()+"="+e.getValue()+"; Path=/; Secure; HttpOnly; SameSite=Lax"); }
            cm.flush();
        }
    }
}
