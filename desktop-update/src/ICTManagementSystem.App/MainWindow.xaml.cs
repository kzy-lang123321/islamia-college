using System.IO;
using System.Windows;
using ICTManagementSystem.App.Bridge;
using Microsoft.Web.WebView2.Core;

namespace ICTManagementSystem.App;

public partial class MainWindow : Window
{
    private readonly JsBridge _bridge;

    public MainWindow(JsBridge bridge)
    {
        InitializeComponent();
        _bridge = bridge;
        Loaded += MainWindow_Loaded;
    }

    private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
    {
        await InitializeWebViewAsync();
    }

    private async Task InitializeWebViewAsync()
    {
        try
        {
            var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            var userDataFolder = Path.Combine(localAppData, "ICTManagementSystem", "webview2_cache");

            var env = await CoreWebView2Environment.CreateAsync(null, userDataFolder);
            await WebViewControl.EnsureCoreWebView2Async(env);

            WebViewControl.CoreWebView2.Settings.IsZoomControlEnabled = false;
            WebViewControl.CoreWebView2.Settings.AreDevToolsEnabled = true;
            WebViewControl.CoreWebView2.Settings.IsStatusBarEnabled = false;

            // Expose the bridge host object
            WebViewControl.CoreWebView2.AddHostObjectToScript("bridge", _bridge);

            // Handle Web Messages as alternative channel
            WebViewControl.CoreWebView2.WebMessageReceived += CoreWebView2_WebMessageReceived;

            // Set up virtual host mapping for wwwroot using AppContext.BaseDirectory
            var wwwroot = Path.Combine(AppContext.BaseDirectory, "wwwroot");
            var indexHtml = Path.Combine(wwwroot, "index.html");

            if (!File.Exists(indexHtml))
            {
                MessageBox.Show(
                    $"Frontend assets are missing.\nExpected index.html at:\n{indexHtml}\n\nPlease reinstall or rebuild the application.",
                    "Frontend Assets Missing",
                    MessageBoxButton.OK,
                    MessageBoxImage.Error);
                return;
            }

            var content = await File.ReadAllTextAsync(indexHtml);
            if (content.Contains("Building frontend assets", StringComparison.OrdinalIgnoreCase))
            {
                MessageBox.Show(
                    $"The application wwwroot contains a placeholder page instead of the production React build.\nFile:\n{indexHtml}\n\nPlease run the build script to compile the frontend assets.",
                    "Invalid Frontend Assets",
                    MessageBoxButton.OK,
                    MessageBoxImage.Error);
                return;
            }

            WebViewControl.CoreWebView2.SetVirtualHostNameToFolderMapping(
                "app.local",
                wwwroot,
                CoreWebView2HostResourceAccessKind.Allow);

            WebViewControl.CoreWebView2.Navigate("https://app.local/index.html");
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Failed to initialize application WebView2 environment: {ex.Message}", "Startup Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private async void CoreWebView2_WebMessageReceived(object? sender, CoreWebView2WebMessageReceivedEventArgs e)
    {
        try
        {
            string message = "";
            try { message = e.TryGetWebMessageAsString(); } catch { }
            if (string.IsNullOrWhiteSpace(message))
            {
                try { message = e.WebMessageAsJson; } catch { }
            }
            if (string.IsNullOrWhiteSpace(message)) return;

            using var doc = System.Text.Json.JsonDocument.Parse(message);
            var root = doc.RootElement;
            if (root.ValueKind == System.Text.Json.JsonValueKind.String)
            {
                using var innerDoc = System.Text.Json.JsonDocument.Parse(root.GetString()!);
                await ProcessWebMessageAsync(innerDoc.RootElement);
            }
            else
            {
                await ProcessWebMessageAsync(root);
            }
        }
        catch (Exception ex)
        {
            System.Diagnostics.Debug.WriteLine($"[WebMessageReceived Error] {ex}");
        }
    }

    private async Task ProcessWebMessageAsync(System.Text.Json.JsonElement root)
    {
        if (root.TryGetProperty("type", out var typeEl))
        {
            var typeStr = typeEl.GetString();
            if (typeStr == "PRINT")
            {
                await Dispatcher.InvokeAsync(() => WebViewControl.CoreWebView2.ShowPrintUI(CoreWebView2PrintDialogKind.Browser));
                return;
            }
            else if (typeStr == "RPC" && root.TryGetProperty("rpcId", out var rpcIdEl))
            {
                var rpcId = rpcIdEl.GetString();
                var method = root.GetProperty("method").GetString() ?? "";
                var payload = root.TryGetProperty("payload", out var pEl) ? pEl.GetRawText() : "{}";

                string resultJson;
                try
                {
                    resultJson = await _bridge.InvokeAsync(method, payload);
                }
                catch (Exception ex)
                {
                    resultJson = System.Text.Json.JsonSerializer.Serialize(new { success = false, error = ex.Message });
                }

                var responseMsg = System.Text.Json.JsonSerializer.Serialize(new
                {
                    rpcId = rpcId,
                    response = resultJson
                });

                await Dispatcher.InvokeAsync(() => WebViewControl.CoreWebView2.PostWebMessageAsJson(responseMsg));
            }
        }
    }
}
