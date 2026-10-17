package in.digiti.epdi;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

public final class EpdiMessagingService extends FirebaseMessagingService {
    private static final String CHANNEL_ID = "epdi_login";

    @Override public void onNewToken(String token) {
        // The long-lived trusted-device secret is Keystore protected and is only used by
        // MainActivity. Persist the new FCM token; MainActivity synchronizes it on next open.
        getSharedPreferences("epdi_trusted", MODE_PRIVATE).edit().putString("push_token", token).apply();
    }

    @Override public void onMessageReceived(RemoteMessage message) {
        if (!"desktop_login".equals(message.getData().get("type"))) return;
        String challenge = value(message,"challenge_guid");
        String code = value(message,"display_code");
        String client = value(message,"client_summary");
        getSharedPreferences("epdi_trusted", MODE_PRIVATE).edit()
                .putString("pending_challenge", challenge)
                .putString("pending_code", code)
                .putString("pending_client", client).apply();
        NotificationManager nm=(NotificationManager)getSystemService(Context.NOTIFICATION_SERVICE);
        if(Build.VERSION.SDK_INT>=26)nm.createNotificationChannel(new NotificationChannel(CHANNEL_ID,"Login approvals",NotificationManager.IMPORTANCE_HIGH));
        Intent intent=new Intent(this,MainActivity.class).putExtra("desktop_challenge",challenge).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP|Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pi=PendingIntent.getActivity(this,20108,intent,PendingIntent.FLAG_UPDATE_CURRENT|PendingIntent.FLAG_IMMUTABLE);
        android.app.Notification.Builder b=Build.VERSION.SDK_INT>=26?new android.app.Notification.Builder(this,CHANNEL_ID):new android.app.Notification.Builder(this);
        b.setSmallIcon(android.R.drawable.ic_lock_lock)
                .setContentTitle("EP-DI desktop login")
                .setContentText((client.isEmpty()?"Desktop browser":client)+" · Code "+code)
                .setStyle(new android.app.Notification.BigTextStyle().bigText("Approve desktop login for "+(client.isEmpty()?"your browser":client)+". Matching code: "+code))
                .setContentIntent(pi).setAutoCancel(true).setPriority(android.app.Notification.PRIORITY_HIGH);
        nm.notify(20108,b.build());
    }

    private static String value(RemoteMessage m,String key){String v=m.getData().get(key);return v==null?"":v;}
}
