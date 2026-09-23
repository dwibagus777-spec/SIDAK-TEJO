package com.sidaktejo.enterprise

import android.Manifest
import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.os.Handler
import android.os.Looper
import android.provider.MediaStore
import android.webkit.*
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private var fileUploadCallback: ValueCallback<Array<Uri>>? = null
    private var cameraImageUri: Uri? = null
    private var doubleBackToExitPressedOnce = false

    private val TARGET_URL = "https://sidaktejo.site/dashboard"

    // Request Camera, Fine Location, Coarse Location, Microphone, and Storage permissions on app startup
    private val appPermissionsLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        val cameraGranted = permissions[Manifest.permission.CAMERA] ?: false
        val fineLocationGranted = permissions[Manifest.permission.ACCESS_FINE_LOCATION] ?: false
        val coarseLocationGranted = permissions[Manifest.permission.ACCESS_COARSE_LOCATION] ?: false
        val audioGranted = permissions[Manifest.permission.RECORD_AUDIO] ?: false

        if (cameraGranted && (fineLocationGranted || coarseLocationGranted)) {
            Toast.makeText(this, "Izin Kamera & Lokasi Aktif", Toast.LENGTH_SHORT).show()
        }

        runOnUiThread {
            webView.evaluateJavascript(
                "window.onAndroidPermissionResult && window.onAndroidPermissionResult('RECORD_AUDIO', $audioGranted);",
                null
            )
        }
    }

    // Direct launcher for on-demand audio permission request (e.g. from Web AI Voice Diagnostic)
    private val audioPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        runOnUiThread {
            if (isGranted) {
                Toast.makeText(this, "Izin Mikrofon Aktif", Toast.LENGTH_SHORT).show()
            }
            webView.evaluateJavascript(
                "window.onAndroidPermissionResult && window.onAndroidPermissionResult('RECORD_AUDIO', $isGranted);",
                null
            )
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        setupCookieManager()
        setupWebView()
        setupBackPressHandler()
        checkAndRequestAppPermissions()

        if (savedInstanceState == null) {
            webView.loadUrl(TARGET_URL)
        }
    }

    private fun setupCookieManager() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        cookieManager.setAcceptThirdPartyCookies(webView, true)
    }

    private fun checkAndRequestAppPermissions() {
        val requiredPermissions = mutableListOf(
            Manifest.permission.CAMERA,
            Manifest.permission.RECORD_AUDIO,
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION
        )

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            requiredPermissions.add(Manifest.permission.READ_MEDIA_IMAGES)
        } else {
            requiredPermissions.add(Manifest.permission.READ_EXTERNAL_STORAGE)
        }

        val missingPermissions = requiredPermissions.filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }

        if (missingPermissions.isNotEmpty()) {
            appPermissionsLauncher.launch(missingPermissions.toTypedArray())
        }
    }

    private fun setupWebView() {
        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.setGeolocationEnabled(true)
        settings.mediaPlaybackRequiresUserGesture = false
        settings.mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
        settings.allowFileAccess = true
        settings.allowContentAccess = true

        // Append custom User-Agent to identify Android Native Shell
        settings.userAgentString = settings.userAgentString + " SIDAKTEJO-Android-AppShell/1.0.0"

        // Javascript Interface for Native Android Bridge
        webView.addJavascriptInterface(AndroidBridge(), "AndroidBridge")

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString() ?: return false
                if (url.startsWith("https://sidaktejo.site") || url.startsWith("http://sidaktejo.site")) {
                    return false
                }
                // External links (WhatsApp, Tel, Maps) opened via Intent
                try {
                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                    startActivity(intent)
                    return true
                } catch (e: Exception) {
                    return false
                }
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                // Flush CodeIgniter 4 session cookies to persistent storage
                CookieManager.getInstance().flush()
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            // Auto-grant Geolocation permission to Web Page when "Ambil Lokasi Saya" is clicked
            override fun onGeolocationPermissionsShowPrompt(
                origin: String?,
                callback: GeolocationPermissions.Callback?
            ) {
                callback?.invoke(origin, true, false)
            }

            // Auto-grant WebRTC camera & audio permission on UI thread
            override fun onPermissionRequest(request: PermissionRequest?) {
                request?.let { req ->
                    runOnUiThread {
                        val requestedResources = req.resources
                        val granted = mutableListOf<String>()
                        for (r in requestedResources) {
                            if (r == PermissionRequest.RESOURCE_AUDIO_CAPTURE) {
                                if (ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) {
                                    granted.add(r)
                                }
                            } else if (r == PermissionRequest.RESOURCE_VIDEO_CAPTURE) {
                                if (ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                                    granted.add(r)
                                }
                            } else {
                                granted.add(r)
                            }
                        }
                        if (granted.isNotEmpty()) {
                            req.grant(granted.toTypedArray())
                        } else {
                            req.deny()
                        }
                    }
                }
            }

            // Native Android Camera & Image File Chooser for Uploads
            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                fileUploadCallback?.onReceiveValue(null)
                fileUploadCallback = filePathCallback

                val takePictureIntent = Intent(MediaStore.ACTION_IMAGE_CAPTURE)
                if (takePictureIntent.resolveActivity(packageManager) != null) {
                    var photoFile: File? = null
                    try {
                        val timeStamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
                        val storageDir = getExternalFilesDir(Environment.DIRECTORY_PICTURES)
                        photoFile = File.createTempFile("JPEG_${timeStamp}_", ".jpg", storageDir)
                    } catch (ex: Exception) {
                        ex.printStackTrace()
                    }

                    if (photoFile != null) {
                        cameraImageUri = FileProvider.getUriForFile(
                            this@MainActivity,
                            "$packageName.fileprovider",
                            photoFile
                        )
                        takePictureIntent.putExtra(MediaStore.EXTRA_OUTPUT, cameraImageUri)
                    }
                }

                val contentSelectionIntent = Intent(Intent.ACTION_GET_CONTENT)
                contentSelectionIntent.addCategory(Intent.CATEGORY_OPENABLE)
                contentSelectionIntent.type = "image/*"

                val intentArray: Array<Intent> = takePictureIntent.let { arrayOf(it) }
                val chooserIntent = Intent(Intent.ACTION_CHOOSER)
                chooserIntent.putExtra(Intent.EXTRA_INTENT, contentSelectionIntent)
                chooserIntent.putExtra(Intent.EXTRA_TITLE, "Pilih Sumber Foto Temuan")
                chooserIntent.putExtra(Intent.EXTRA_INITIAL_INTENTS, intentArray)

                filePickerLauncher.launch(chooserIntent)
                return true
            }
        }

        // Native Android DownloadManager Listener with Authenticated Cookie Header (.pptx, .xlsx, .csv, .pdf)
        webView.setDownloadListener { url, userAgent, contentDisposition, mimetype, contentLength ->
            try {
                val request = DownloadManager.Request(Uri.parse(url))
                val filename = URLUtil.guessFileName(url, contentDisposition, mimetype)

                // Pass CodeIgniter 4 session cookie to DownloadManager request
                val cookie = CookieManager.getInstance().getCookie(url)
                if (!cookie.isNullOrEmpty()) {
                    request.addRequestHeader("Cookie", cookie)
                }

                request.setMimeType(mimetype)
                request.addRequestHeader("User-Agent", userAgent)
                request.setDescription("Mengunduh laporan SIDAK TEJO...")
                request.setTitle(filename)
                request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, filename)

                val dm = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
                dm.enqueue(request)
                Toast.makeText(applicationContext, "Mengunduh $filename ... Cek Notifikasi HP", Toast.LENGTH_LONG).show()
            } catch (e: Exception) {
                Toast.makeText(applicationContext, "Gagal mengunduh: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private val filePickerLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (fileUploadCallback == null) return@registerForActivityResult

        var results: Array<Uri>? = null
        if (result.resultCode == RESULT_OK) {
            val data = result.data
            if (data?.data != null) {
                results = arrayOf(data.data!!)
            } else if (cameraImageUri != null) {
                results = arrayOf(cameraImageUri!!)
            }
        }
        fileUploadCallback?.onReceiveValue(results)
        fileUploadCallback = null
    }

    private fun setupBackPressHandler() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    if (doubleBackToExitPressedOnce) {
                        finish()
                        return
                    }
                    doubleBackToExitPressedOnce = true
                    Toast.makeText(this@MainActivity, "Tekan sekali lagi untuk keluar dari SIDAK TEJO", Toast.LENGTH_SHORT).show()
                    Handler(Looper.getMainLooper()).postDelayed({
                        doubleBackToExitPressedOnce = false
                    }, 2000)
                }
            }
        })
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    override fun onRestoreInstanceState(savedInstanceState: Bundle) {
        super.onRestoreInstanceState(savedInstanceState)
        webView.restoreState(savedInstanceState)
    }

    inner class AndroidBridge {
        @JavascriptInterface
        fun isAndroidNativeApp(): Boolean = true

        @JavascriptInterface
        fun getAppVersion(): String = "1.0.1"

        @JavascriptInterface
        fun checkRecordAudioPermission(): Boolean {
            return ContextCompat.checkSelfPermission(
                this@MainActivity,
                Manifest.permission.RECORD_AUDIO
            ) == PackageManager.PERMISSION_GRANTED
        }

        @JavascriptInterface
        fun requestRecordAudioPermission() {
            runOnUiThread {
                audioPermissionLauncher.launch(Manifest.permission.RECORD_AUDIO)
            }
        }
    }
}
