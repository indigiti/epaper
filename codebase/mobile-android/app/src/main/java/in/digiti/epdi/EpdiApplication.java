package in.digiti.epdi;

import android.app.Application;
import com.google.firebase.FirebaseApp;
import com.google.firebase.FirebaseOptions;

public final class EpdiApplication extends Application {
    private static boolean firebaseReady = false;
    @Override public void onCreate() {
        super.onCreate();
        if (!BuildConfig.FIREBASE_APPLICATION_ID.isEmpty() && !BuildConfig.FIREBASE_API_KEY.isEmpty()
                && !BuildConfig.FIREBASE_PROJECT_ID.isEmpty() && !BuildConfig.FIREBASE_SENDER_ID.isEmpty()) {
            try {
                FirebaseOptions options = new FirebaseOptions.Builder()
                        .setApplicationId(BuildConfig.FIREBASE_APPLICATION_ID)
                        .setApiKey(BuildConfig.FIREBASE_API_KEY)
                        .setProjectId(BuildConfig.FIREBASE_PROJECT_ID)
                        .setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
                        .build();
                if (FirebaseApp.getApps(this).isEmpty()) FirebaseApp.initializeApp(this, options);
                firebaseReady = true;
            } catch (Throwable ignored) { firebaseReady = false; }
        }
    }
    public static boolean firebaseReady() { return firebaseReady; }
}
