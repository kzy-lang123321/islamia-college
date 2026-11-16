using System.IO;
using System.Threading;
using System.Windows;
using ICTManagementSystem.App.Bridge;
using ICTManagementSystem.App.Services.Backup;
using ICTManagementSystem.Core.Interfaces;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Interceptors;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Serilog;

namespace ICTManagementSystem.App;

public partial class App : Application
{
    private Mutex? _singleInstanceMutex;
    private IHost? _host;

    protected override async void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);

        // 1. Single Instance Named Mutex
        const string mutexName = "ICTManagementSystem_SingleInstance_Mutex";
        _singleInstanceMutex = new Mutex(true, mutexName, out bool isOnlyInstance);

        if (!isOnlyInstance)
        {
            MessageBox.Show(
                "ICT Management System is already running.\nOnly one instance is permitted at a time to protect financial database integrity.",
                "Application Already Open",
                MessageBoxButton.OK,
                MessageBoxImage.Warning);
            Shutdown();
            return;
        }

        // 2. Setup Local Paths
        var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        var appFolder = Path.Combine(localAppData, "ICTManagementSystem");
        var dataFolder = Path.Combine(appFolder, "data");
        var logsFolder = Path.Combine(appFolder, "logs");
        var backupsFolder = Path.Combine(appFolder, "backups");

        Directory.CreateDirectory(dataFolder);
        Directory.CreateDirectory(logsFolder);
        Directory.CreateDirectory(backupsFolder);

        // 3. Configure Serilog Rolling File
        var logFile = Path.Combine(logsFolder, "ict-.log");
        Log.Logger = new LoggerConfiguration()
            .MinimumLevel.Information()
            .WriteTo.File(logFile, rollingInterval: RollingInterval.Day, retainedFileCountLimit: 14)
            .CreateLogger();

        Log.Information("ICT Management System starting up...");

        try
        {
            var dbPath = Path.Combine(dataFolder, "app.db");
            var queueDbPath = Path.Combine(dataFolder, "sync-queue.db");

            // 4. Build Host with Dependency Injection
            _host = Host.CreateDefaultBuilder()
                .UseSerilog()
                .ConfigureServices((context, services) =>
                {
                    // EF Core AppDbContext with Interceptor & WAL
                    services.AddSingleton<SqlitePragmaInterceptor>();
                    services.AddDbContext<AppDbContext>((sp, options) =>
                    {
                        var interceptor = sp.GetRequiredService<SqlitePragmaInterceptor>();
                        options.UseSqlite($"Data Source={dbPath}")
                               .AddInterceptors(interceptor);
                    });

                    // SyncQueueDbContext
                    services.AddDbContext<SyncQueueDbContext>(options =>
                    {
                        options.UseSqlite($"Data Source={queueDbPath}");
                    });

                    // Core Domain Services
                    services.AddScoped<IAuthService, AuthService>();
                    services.AddScoped<IStudentService, StudentService>();
                    services.AddScoped<ICourseService, CourseService>();
                    services.AddScoped<IFeeService, FeeService>();
                    services.AddScoped<IAttendanceService, AttendanceService>();
                    services.AddScoped<IResultService, ResultService>();
                    services.AddScoped<ICertificateService, CertificateService>();
                    services.AddScoped<IDashboardService, DashboardService>();
                    services.AddScoped<IDataImportService, DataImportService>();

                    // Backup & Sync Services
                    services.AddSingleton<IGoogleDriveClient>(new GoogleDriveBackupService(appFolder));
                    services.AddSingleton(sp => new SqliteBackupEngine(dbPath, backupsFolder, sp.GetRequiredService<SyncQueueDbContext>()));
                    services.AddScoped<IBackupService, BackupService>();

                    // Background Hosted Worker
                    services.AddHostedService<SyncQueueWorker>();

                    // Bridge & UI
                    services.AddSingleton<JsBridge>();
                    services.AddSingleton<MainWindow>();
                })
                .Build();

            await _host.StartAsync();

            // 5. Database Health Check & Schema Initialization
            using (var scope = _host.Services.CreateScope())
            {
                var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();
                await db.Database.EnsureCreatedAsync();

                // Drop obsolete supplier tables safely
                try
                {
                    await db.Database.ExecuteSqlRawAsync("DROP TABLE IF EXISTS SupplierPayments; DROP TABLE IF EXISTS SupplierBills; DROP TABLE IF EXISTS Suppliers;");
                }
                catch { }

                // Quick integrity check
                using (var conn = new SqliteConnection($"Data Source={dbPath};Mode=ReadOnly"))
                {
                    await conn.OpenAsync();
                    using var checkCmd = conn.CreateCommand();
                    checkCmd.CommandText = "PRAGMA quick_check;";
                    var status = (string?)await checkCmd.ExecuteScalarAsync();
                    if (status != "ok")
                    {
                        Log.Warning("Startup PRAGMA quick_check reported non-ok status: {Status}", status);
                    }
                }

                // Seed Default Admin and Settings
                await AppDbContext.SeedInitialDataAsync(db);

                // Auto-import MySQL database from XAMPP web system if not yet populated
                try
                {
                    var studentCount = await db.Students.CountAsync();
                    var candidatePaths = new[]
                    {
                        Path.Combine(AppContext.BaseDirectory, "data", "live_mysql_dump.sql"),
                        @"E:\ICTManagementSystem\data\live_mysql_dump.sql",
                        @"c:\xampp\htdocs\islamia college\database.sql"
                    };

                    var mySqlDumpPath = candidatePaths.FirstOrDefault(File.Exists);

                    if (studentCount < 100 && mySqlDumpPath != null)
                    {
                        Log.Information("Detecting MySQL database dump at {Path}. Auto-migrating data into SQLite...", mySqlDumpPath);
                        var importer = scope.ServiceProvider.GetRequiredService<IDataImportService>();
                        var importRes = await importer.ExecuteImportAsync(mySqlDumpPath);
                        Log.Information("MySQL data import result: {Result}", importRes.Message);
                    }
                }
                catch (Exception ex)
                {
                    Log.Warning(ex, "Auto-migration from MySQL dump failed or skipped");
                }

                var queueDb = scope.ServiceProvider.GetRequiredService<SyncQueueDbContext>();
                await queueDb.Database.EnsureCreatedAsync();
            }

            // 6. Launch Main Window
            var mainWindow = _host.Services.GetRequiredService<MainWindow>();
            mainWindow.Show();
        }
        catch (Exception ex)
        {
            Log.Fatal(ex, "Fatal application startup failure");
            MessageBox.Show($"Application failed to start: {ex.Message}", "Fatal Error", MessageBoxButton.OK, MessageBoxImage.Error);
            Shutdown();
        }
    }

    protected override async void OnExit(ExitEventArgs e)
    {
        Log.Information("ICT Management System shutting down...");

        try
        {
            var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            var dbPath = Path.Combine(localAppData, "ICTManagementSystem", "data", "app.db");

            if (File.Exists(dbPath))
            {
                using var conn = new SqliteConnection($"Data Source={dbPath}");
                await conn.OpenAsync();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "PRAGMA wal_checkpoint(TRUNCATE); PRAGMA optimize;";
                await cmd.ExecuteNonQueryAsync();
            }

            if (_host != null)
            {
                await _host.StopAsync(TimeSpan.FromSeconds(5));
                _host.Dispose();
            }
        }
        catch (Exception ex)
        {
            Log.Error(ex, "Error during exit shutdown cleanup");
        }
        finally
        {
            _singleInstanceMutex?.ReleaseMutex();
            _singleInstanceMutex?.Dispose();
            Log.CloseAndFlush();
            base.OnExit(e);
        }
    }
}
