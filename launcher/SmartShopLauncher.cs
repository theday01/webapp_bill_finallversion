using System;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Threading;
using System.Windows.Forms;

namespace SmartShopLauncher
{
    public class MainForm : Form
    {
        // --- Windows API for Rounded Corners and Dragging ---
        [DllImport("Gdi32.dll", EntryPoint = "CreateRoundRectRgn")]
        private static extern IntPtr CreateRoundRectRgn(int nLeftRect, int nTopRect, int nRightRect, int nBottomRect, int nWidthEllipse, int nHeightEllipse);

        public const int WM_NCLBUTTONDOWN = 0xA1;
        public const int HT_CAPTION = 0x2;
        [DllImportAttribute("user32.dll")]
        public static extern int SendMessage(IntPtr hWnd, int Msg, int wParam, int lParam);
        [DllImportAttribute("user32.dll")]
        public static extern bool ReleaseCapture();
        // ----------------------------------------------------

        private Panel topBar;
        private Panel sidebarPanel;
        private Panel contentPanel;
        private Panel statusPanel;
        private Label lblTitle;
        private RichTextBox logBox;
        
        private Button btnStart;
        private Button btnStop;
        private Button btnOpenBrowser;
        private Button btnDbConsole;
        private Button btnContact;
        
        private Label lblStatusDB;
        private Label lblStatusWeb;
        
        private NotifyIcon trayIcon;
        private ContextMenuStrip trayMenu;

        private Process procMySQL;
        private Process procPHP;
        
        private string appDir;
        private string binPhp;
        private string binMySQL;
        private string dataDir;
        private string wwwDir;
        
        private const int DB_PORT = 3307;
        private const int PHP_PORT = 8000;
        
        private Color clrBackground = Color.FromArgb(13, 13, 15);
        private Color clrSidebar = Color.FromArgb(22, 22, 25);   
        private Color clrCard = Color.FromArgb(30, 30, 35);      
        private Color clrAccent = Color.FromArgb(0, 122, 204);   
        private Color clrText = Color.White;
        private Color clrTextDim = Color.FromArgb(140, 140, 140);
        private Color clrSuccess = Color.FromArgb(40, 167, 69);
        private Color clrDanger = Color.FromArgb(220, 53, 69);

        public MainForm()
        {
            InitializePaths(); 
            
            // Start Splash Screen on a separate thread
            Thread splashThread = new Thread(() => {
                Application.Run(new SplashScreen());
            });
            splashThread.SetApartmentState(ApartmentState.STA);
            splashThread.Start();
            
            // Wait while splash screen is showing (simulating load time as requested)
            // The splash screen takes about 3.5 seconds to fill the bar.
            // We sleep to let it run.
            Thread.Sleep(3500);

            InitializeComponent();
            
            this.Region = Region.FromHrgn(CreateRoundRectRgn(0, 0, this.Width, this.Height, 20, 20));

            // Signal Splash Screen to close? 
            // Since we don't have a direct reference to the form instance created in the thread,
            // we can rely on the Splash Screen's internal timer to close itself, 
            // OR we can use a simpler approach where we just wait for the thread.
            // But since the Splash Screen has its own logic, let's assume it closes itself after its animation or we force it?
            // Actually, a better way is to let the Splash Screen stay open until we are ready.
            // But for simplicity and to match the "Thread.Sleep" request, we just let the Splash Screen run its animation and close itself.
            // However, to be robust, if the Splash Screen is still open, we should technically wait or signal it.
            // For this implementation, the SplashScreen will be designed to close itself after animation completes.
        }

        private void InitializePaths()
        {
            appDir = AppDomain.CurrentDomain.BaseDirectory;
            if (File.Exists(Path.Combine(appDir, "www", "login.php"))) {
                wwwDir = Path.Combine(appDir, "www");
            } else if (File.Exists(Path.Combine(appDir, "..", "login.php"))) {
                wwwDir = Path.Combine(appDir, "..");
            } else {
                wwwDir = Path.Combine(appDir, "www");
            }

            binPhp = Path.Combine(appDir, "bin", "php", "php.exe");
            binMySQL = Path.Combine(appDir, "bin", "mysql", "bin", "mysqld.exe");
            string appData = Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData);
            dataDir = Path.Combine(appData, "SmartShop", "data");
        }

        private void InitializeComponent()
        {
            this.Text = "Smart Shop Launcher";
            this.Size = new Size(1000, 650);
            this.FormBorderStyle = FormBorderStyle.None; 
            this.StartPosition = FormStartPosition.CenterScreen;
            this.BackColor = clrBackground;
            this.Icon = SystemIcons.Application; 

            trayMenu = new ContextMenuStrip();
            trayMenu.Items.Add("Open Control Panel", null, (s, e) => RestoreWindow());
            trayMenu.Items.Add(new ToolStripSeparator());
            trayMenu.Items.Add("Exit System", null, (s, e) => this.Close());

            trayIcon = new NotifyIcon();
            trayIcon.Text = "Smart Shop Server";
            trayIcon.Icon = SystemIcons.Application; 
            trayIcon.ContextMenuStrip = trayMenu;
            trayIcon.Visible = false;

            trayIcon.DoubleClick += (s, e) => RestoreWindow();
            this.Resize += MainForm_Resize; 

            topBar = new Panel { Dock = DockStyle.Top, Height = 35, BackColor = clrSidebar };
            topBar.MouseDown += TopBar_MouseDown; 
            this.Controls.Add(topBar);

            Label lblAppTitle = new Label { Text = "EagleShadow Technology - SmartShop Launcher", ForeColor = clrTextDim, Font = new Font("Segoe UI", 9), Location = new Point(15, 9), AutoSize = true };
            lblAppTitle.MouseDown += TopBar_MouseDown;
            topBar.Controls.Add(lblAppTitle);

            Button btnClose = new Button { Text = "✕", Dock = DockStyle.Right, Width = 45, FlatStyle = FlatStyle.Flat, ForeColor = clrTextDim, Font = new Font("Segoe UI", 10), Cursor = Cursors.Hand };
            btnClose.FlatAppearance.BorderSize = 0;
            btnClose.FlatAppearance.MouseOverBackColor = Color.FromArgb(220, 53, 69);
            btnClose.MouseEnter += (s, e) => btnClose.ForeColor = Color.White;
            btnClose.MouseLeave += (s, e) => btnClose.ForeColor = clrTextDim;
            btnClose.Click += (s, e) => this.Close(); // Calls FormClosing automatically
            topBar.Controls.Add(btnClose);

            Button btnMin = new Button { Text = "—", Dock = DockStyle.Right, Width = 45, FlatStyle = FlatStyle.Flat, ForeColor = clrTextDim, Font = new Font("Segoe UI", 10, FontStyle.Bold), Cursor = Cursors.Hand };
            btnMin.FlatAppearance.BorderSize = 0;
            btnMin.FlatAppearance.MouseOverBackColor = Color.FromArgb(50, 50, 50);
            btnMin.MouseEnter += (s, e) => btnMin.ForeColor = Color.White;
            btnMin.MouseLeave += (s, e) => btnMin.ForeColor = clrTextDim;
            btnMin.Click += (s, e) => this.WindowState = FormWindowState.Minimized;
            topBar.Controls.Add(btnMin);

            sidebarPanel = new Panel { Dock = DockStyle.Left, Width = 220, BackColor = clrSidebar };
            this.Controls.Add(sidebarPanel);

            Panel topSection = new Panel { Dock = DockStyle.Top, Height = 120 };
            sidebarPanel.Controls.Add(topSection);

            lblTitle = new Label { Text = "Smart Shop\nSystem", Font = new Font("Segoe UI Light", 18), ForeColor = clrText, TextAlign = ContentAlignment.MiddleCenter, Dock = DockStyle.Fill };
            topSection.Controls.Add(lblTitle);

            Panel menuContainer = new Panel { Dock = DockStyle.Fill, Padding = new Padding(0, 10, 0, 0) };
            sidebarPanel.Controls.Add(menuContainer);
            menuContainer.BringToFront(); 
            
            btnStart = CreateSidebarButton("▶  Start System", clrAccent);
            btnStart.Click += BtnStart_Click;
            menuContainer.Controls.Add(btnStart);

            btnStop = CreateSidebarButton("■  Stop System", clrDanger);
            btnStop.Click += BtnStop_Click;
            btnStop.Enabled = false; 
            menuContainer.Controls.Add(btnStop);

            btnOpenBrowser = CreateSidebarButton("🌐  Open Browser", clrSidebar);
            btnOpenBrowser.Click += BtnOpenBrowser_Click;
            btnOpenBrowser.Enabled = false; 
            menuContainer.Controls.Add(btnOpenBrowser);

            btnDbConsole = CreateSidebarButton("🗄  DB Console", clrSidebar);
            btnDbConsole.Click += BtnDbConsole_Click;
            btnDbConsole.Enabled = false; 
            menuContainer.Controls.Add(btnDbConsole);
            
            btnContact = CreateSidebarButton("✉  Contact Us", clrSidebar);
            btnContact.Click += BtnContact_Click;
            btnContact.Dock = DockStyle.Bottom;
            sidebarPanel.Controls.Add(btnContact);

            contentPanel = new Panel { Dock = DockStyle.Fill, Padding = new Padding(30) };
            this.Controls.Add(contentPanel);
            
            sidebarPanel.SendToBack();
            topBar.SendToBack();
            contentPanel.BringToFront();

            statusPanel = new Panel { Dock = DockStyle.Top, Height = 100 };
            
            Panel cardDB = new Panel { Size = new Size(330, 80), Location = new Point(0, 0), BackColor = clrCard };
            cardDB.Region = Region.FromHrgn(CreateRoundRectRgn(0, 0, cardDB.Width, cardDB.Height, 10, 10));
            statusPanel.Controls.Add(cardDB);
            
            Label lblDBHeader = new Label { Text = "DATABASE STATUS", Location = new Point(20, 15), AutoSize = true, Font = new Font("Segoe UI", 9, FontStyle.Bold), ForeColor = clrTextDim };
            cardDB.Controls.Add(lblDBHeader);
            lblStatusDB = new Label { Text = "STOPPED", Location = new Point(20, 40), AutoSize = true, Font = new Font("Segoe UI Light", 16), ForeColor = clrDanger };
            cardDB.Controls.Add(lblStatusDB);

            Panel cardWeb = new Panel { Size = new Size(330, 80), Location = new Point(350, 0), BackColor = clrCard };
            cardWeb.Region = Region.FromHrgn(CreateRoundRectRgn(0, 0, cardWeb.Width, cardWeb.Height, 10, 10));
            statusPanel.Controls.Add(cardWeb);

            Label lblWebHeader = new Label { Text = "WEB SERVER STATUS", Location = new Point(20, 15), AutoSize = true, Font = new Font("Segoe UI", 9, FontStyle.Bold), ForeColor = clrTextDim };
            cardWeb.Controls.Add(lblWebHeader);
            lblStatusWeb = new Label { Text = "STOPPED", Location = new Point(20, 40), AutoSize = true, Font = new Font("Segoe UI Light", 16), ForeColor = clrDanger };
            cardWeb.Controls.Add(lblStatusWeb);

            Panel logWrapper = new Panel { Dock = DockStyle.Fill, Padding = new Padding(0, 20, 0, 0), BackColor = clrBackground };
            Label lblLogHeader = new Label { Text = "System Console", Dock = DockStyle.Top, Height = 35, TextAlign = ContentAlignment.BottomLeft, Font = new Font("Segoe UI", 11), ForeColor = clrTextDim };
            Panel logInner = new Panel { Dock = DockStyle.Fill, BackColor = Color.FromArgb(8, 8, 10), Padding = new Padding(10) };
            
            logBox = new RichTextBox { Dock = DockStyle.Fill, ReadOnly = true, BackColor = Color.FromArgb(8, 8, 10), ForeColor = Color.FromArgb(200, 200, 200), Font = new Font("Consolas", 9), BorderStyle = BorderStyle.None };
            
            logInner.Controls.Add(logBox);
            
            logWrapper.Controls.Add(logInner);
            logWrapper.Controls.Add(lblLogHeader);
            logInner.BringToFront(); 
            
            contentPanel.Controls.Add(statusPanel);
            contentPanel.Controls.Add(logWrapper);
            
            logWrapper.BringToFront(); 

            this.FormClosing += MainForm_FormClosing;
        }

        private void MainForm_Resize(object sender, EventArgs e)
        {
            if (this.WindowState == FormWindowState.Minimized)
            {
                this.Hide(); 
                trayIcon.Visible = true;
                trayIcon.ShowBalloonTip(2000, "Smart Shop System", "The server is running in the background. Double click here to open.", ToolTipIcon.Info);
            }
        }

        private void RestoreWindow()
        {
            this.Show(); 
            this.WindowState = FormWindowState.Normal; 
            trayIcon.Visible = false; 
        }

        private void TopBar_MouseDown(object sender, MouseEventArgs e)
        {
            if (e.Button == MouseButtons.Left)
            {
                ReleaseCapture();
                SendMessage(Handle, WM_NCLBUTTONDOWN, HT_CAPTION, 0);
            }
        }

        private Button CreateSidebarButton(string text, Color enabledColor)
        {
            Button btn = new Button { Text = text, Height = 55, Dock = DockStyle.Top, FlatStyle = FlatStyle.Flat, BackColor = enabledColor, ForeColor = clrText, Font = new Font("Segoe UI", 10), TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(25, 0, 0, 0), Cursor = Cursors.Hand };
            btn.FlatAppearance.BorderSize = 0;
            
            btn.EnabledChanged += (s, e) => {
                if (btn.Enabled) { btn.BackColor = enabledColor; btn.ForeColor = clrText; } 
                else { btn.BackColor = Color.FromArgb(30, 30, 35); btn.ForeColor = Color.FromArgb(100, 100, 100); }
            };
            
            btn.MouseEnter += (s, e) => { 
                if(btn.Enabled) {
                    btn.BackColor = Color.FromArgb(Math.Min(255, enabledColor.R + 15), Math.Min(255, enabledColor.G + 15), Math.Min(255, enabledColor.B + 15));
                }
            };
            btn.MouseLeave += (s, e) => { if(btn.Enabled) btn.BackColor = enabledColor; };
            
            return btn;
        }

        private void Log(string message)
        {
            if (string.IsNullOrEmpty(message)) return;

            if (logBox.InvokeRequired)
            {
                logBox.Invoke(new Action<string>(Log), message);
                return;
            }
            
            Color col = Color.FromArgb(200, 200, 200);
            if (message.Contains("Error") || message.Contains("Failed") || message.Contains("Exception")) col = clrDanger;
            else if (message.Contains("ready") || message.Contains("started") || message.Contains("RUNNING") || message.Contains("successfully")) col = clrSuccess;
            else if (message.Contains("Warning")) col = Color.FromArgb(255, 193, 7);

            logBox.SelectionStart = logBox.TextLength;
            logBox.SelectionLength = 0;
            logBox.SelectionColor = Color.FromArgb(100, 100, 100); 
            logBox.AppendText(String.Format("[{0:HH:mm:ss}] ", DateTime.Now));
            
            logBox.SelectionStart = logBox.TextLength;
            logBox.SelectionLength = 0;
            logBox.SelectionColor = col; 
            logBox.AppendText(message + "\n");
            
            logBox.ScrollToCaret();
        }

        private void ResetStartButton()
        {
            if (this.InvokeRequired)
            {
                this.Invoke(new Action(ResetStartButton));
                return;
            }
            btnStart.Enabled = true;
        }

        private void BtnStart_Click(object sender, EventArgs e)
        {
            btnStart.Enabled = false; 
            logBox.Clear(); 
            Log("Initializing startup sequence...");
            
            Thread thread = new Thread(StartSequence);
            thread.IsBackground = true;
            thread.Start();
        }

        private void StartSequence()
        {
            try 
            {
                if (!File.Exists(binMySQL)) { Log("Error: MySQL binary not found at:\n" + binMySQL); ResetStartButton(); return; }
                if (!File.Exists(binPhp)) { Log("Error: PHP binary not found at:\n" + binPhp); ResetStartButton(); return; }

                if (!Directory.Exists(dataDir))
                {
                    Log("Initializing Database for the first time...");
                    try
                    {
                        Directory.CreateDirectory(dataDir);
                        ProcessStartInfo psiInit = new ProcessStartInfo
                        {
                            FileName = binMySQL,
                            Arguments = String.Format("--initialize-insecure --datadir=\"{0}\" --console", dataDir),
                            UseShellExecute = false,
                            CreateNoWindow = true,
                            RedirectStandardError = true,
                            RedirectStandardOutput = true
                        };
                        using (Process p = Process.Start(psiInit))
                        {
                            p.WaitForExit();
                            if (p.ExitCode != 0) { Log("Error initializing DB: " + p.StandardError.ReadToEnd()); ResetStartButton(); return; }
                        }
                        Log("Database initialized successfully.");
                    }
                    catch (Exception ex) { Log("Exception init DB: " + ex.Message); ResetStartButton(); return; }
                }

                Log("Starting MySQL Server on port " + DB_PORT + "...");
                try
                {
                    ProcessStartInfo psiSql = new ProcessStartInfo
                    {
                        FileName = binMySQL,
                        Arguments = String.Format("--bind-address=127.0.0.1 --port={0} --datadir=\"{1}\" --console", DB_PORT, dataDir),
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };
                    
                    procMySQL = new Process();
                    procMySQL.StartInfo = psiSql;
                    
                    procMySQL.OutputDataReceived += (s, ev) => { if (!string.IsNullOrEmpty(ev.Data)) Log("[MySQL] " + ev.Data); };
                    procMySQL.ErrorDataReceived += (s, ev) => { 
                        if (!string.IsNullOrEmpty(ev.Data)) {
                            string msg = ev.Data;
                            if (msg.Contains("[System]") || msg.Contains("[Note]") || msg.Contains("ready for connections")) Log("[MySQL System] " + msg);
                            else if (msg.Contains("[Warning]")) Log("[MySQL Warning] " + msg);
                            else if (msg.Contains("[ERROR]")) Log("[MySQL Error] " + msg);
                            else Log("[MySQL Info] " + msg); 
                        }
                    };
                    
                    procMySQL.Start();
                    procMySQL.BeginOutputReadLine();
                    procMySQL.BeginErrorReadLine();

                    UpdateStatus(lblStatusDB, "RUNNING", clrSuccess);
                }
                catch (Exception ex)
                {
                    Log("Failed to start MySQL: " + ex.Message);
                    ResetStartButton();
                    return;
                }

                bool dbReady = false;
                for (int i = 0; i < 30; i++)
                {
                    try { using (TcpClient client = new TcpClient()) { client.Connect("127.0.0.1", DB_PORT); dbReady = true; break; } }
                    catch { }
                    Thread.Sleep(1000);
                }

                if (!dbReady)
                {
                    Log("Error: Database failed to start. Check if port is in use or VC++ Redistributable is missing.");
                    StopProcesses();
                    ResetStartButton();
                    return;
                }
                Log("Database is ready.");

                try { File.WriteAllText(Path.Combine(wwwDir, "portable_config.php"), String.Format("<?php $PORTABLE_DB_PORT = {0};", DB_PORT)); } 
                catch (Exception ex) { Log("Warning: Could not write portable_config.php: " + ex.Message); }

                Log("Starting PHP Web Server on port " + PHP_PORT + "...");
                try
                {
                    string extDir = File.Exists(Path.Combine(appDir, "bin", "php", "php.exe")) ? "bin\\php\\ext" : "..\\bin\\php\\ext";
                    string phpArgs = String.Format("-d extension_dir=\"{0}\" -d extension=mysqli -d extension=mbstring -d extension=gd -d extension=curl -d upload_max_filesize=64M -d post_max_size=64M -d memory_limit=256M -S 127.0.0.1:{1} -t \"{2}\"", extDir, PHP_PORT, wwwDir);

                    ProcessStartInfo psiPhp = new ProcessStartInfo
                    {
                        FileName = binPhp,
                        Arguments = phpArgs,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };

                    procPHP = new Process();
                    procPHP.StartInfo = psiPhp;
                    
                    procPHP.OutputDataReceived += (s, ev) => { 
                        if (!string.IsNullOrEmpty(ev.Data)) {
                            string msg = ev.Data;
                            if (msg.Contains("Development Server") || msg.Contains("started")) return;
                            Log("[PHP] " + msg); 
                        }
                    };
                    
                    procPHP.ErrorDataReceived += (s, ev) => { 
                        if (!string.IsNullOrEmpty(ev.Data)) {
                            string msg = ev.Data;
                            if (msg.Contains("Development Server") || msg.Contains("started")) return;
                            if (msg.Contains("Fatal error") || msg.Contains("Parse error")) Log("[PHP Error] " + msg);
                            else if (msg.Contains("Warning")) Log("[PHP Warning] " + msg);
                            else if (msg.Contains("Notice") || msg.Contains("Deprecated")) Log("[PHP Info] " + msg);
                            else Log("[PHP System] " + msg);
                        }
                    };
                    
                    procPHP.Start();
                    procPHP.BeginOutputReadLine();
                    procPHP.BeginErrorReadLine();

                    UpdateStatus(lblStatusWeb, "RUNNING", clrSuccess);
                }
                catch (Exception ex)
                {
                    Log("Failed to start PHP: " + ex.Message);
                    StopProcesses();
                    ResetStartButton();
                    return;
                }

                this.Invoke(new Action(() => {
                    btnStop.Enabled = true;
                    btnOpenBrowser.Enabled = true;
                    btnDbConsole.Enabled = true;
                    
                    if (trayIcon.Visible) {
                         trayIcon.ShowBalloonTip(3000, "System Ready", "Smart Shop Server is now running. Click 'Open Browser' to access it.", ToolTipIcon.Info);
                    }
                    
                    BtnOpenBrowser_Click(null, null);
                }));
            }
            catch (Exception ex)
            {
                Log("Critical System Error: " + ex.Message);
                StopProcesses();
                ResetStartButton();
            }
        }

        private void BtnStop_Click(object sender, EventArgs e)
        {
            StopProcesses();
            btnStart.Enabled = true; 
            btnStop.Enabled = false; 
            btnOpenBrowser.Enabled = false; 
            btnDbConsole.Enabled = false; 
            UpdateStatus(lblStatusDB, "STOPPED", clrDanger);
            UpdateStatus(lblStatusWeb, "STOPPED", clrDanger);
            Log("System stopped manually.");
        }

        private void StopProcesses()
        {
            try { if (procPHP != null && !procPHP.HasExited) { procPHP.Kill(); procPHP = null; } } catch {}

            try {
                if (procMySQL != null && !procMySQL.HasExited) {
                    string adminBin = Path.Combine(appDir, "bin", "mysql", "bin", "mysqladmin.exe");
                    if (File.Exists(adminBin)) {
                        Process pShutdown = Process.Start(new ProcessStartInfo { FileName = adminBin, Arguments = String.Format("-u root --port={0} shutdown", DB_PORT), UseShellExecute = false, CreateNoWindow = true });
                        if (pShutdown != null) pShutdown.WaitForExit(3000);
                    }
                    if (!procMySQL.HasExited) procMySQL.Kill();
                    procMySQL = null;
                }
            } catch {}
        }

        private void BtnOpenBrowser_Click(object sender, EventArgs e) { Process.Start(String.Format("http://localhost:{0}", PHP_PORT)); }

        private void BtnDbConsole_Click(object sender, EventArgs e)
        {
            try { new DbQueryForm(appDir, DB_PORT).Show(); }
            catch (Exception ex) { Log("Error opening DB Console: " + ex.Message); }
        }
        
        private void BtnContact_Click(object sender, EventArgs e) { new ContactForm().ShowDialog(); }

        // --- ميزة الحماية عند محاولة الإغلاق ---
        private void MainForm_FormClosing(object sender, FormClosingEventArgs e)
        {
            // التحقق مما إذا كان النظام قيد التشغيل (عن طريق التأكد من أن زر Stop مفعل)
            if (btnStop.Enabled)
            {
                MessageBox.Show("The system is currently running in the background.\nPlease stop it by pressing the 'Stop System' button first before attempting to close the program.", "SmartShop System Launcher", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                e.Cancel = true; // إلغاء عملية الإغلاق
                return;
            }

            StopProcesses();
            
            if (trayIcon != null)
            {
                trayIcon.Visible = false;
                trayIcon.Dispose();
            }
        }

        private void UpdateStatus(Label lbl, string text, Color color)
        {
            if (lbl.InvokeRequired) { lbl.Invoke(new Action<Label, string, Color>(UpdateStatus), lbl, text, color); return; }
            lbl.Text = text;
            lbl.ForeColor = color;
        }

        [STAThread]
        static void Main()
        {
            bool createdNew;
            using (Mutex mutex = new Mutex(true, "Global\\SmartShopLauncherMutex", out createdNew))
            {
                if (!createdNew) { MessageBox.Show("Smart Shop is already running.", "Smart Shop", MessageBoxButtons.OK, MessageBoxIcon.Information); return; }
                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new MainForm());
            }
        }
        
        public class SplashScreen : Form
        {
            [DllImport("Gdi32.dll", EntryPoint = "CreateRoundRectRgn")]
            private static extern IntPtr CreateRoundRectRgn(int nLeftRect, int nTopRect, int nRightRect, int nBottomRect, int nWidthEllipse, int nHeightEllipse);

            private System.Windows.Forms.Timer timer;
            private int progress = 0;
            private string statusText = "Initializing...";
            private string[] loadingSteps = { "Initializing secure environment...", "Loading system modules...", "Verifying database connection...", "Starting background services...", "Preparing user interface..." };
            
            public SplashScreen()
            {
                this.Size = new Size(600, 400);
                this.FormBorderStyle = FormBorderStyle.None;
                this.StartPosition = FormStartPosition.CenterScreen;
                this.BackColor = Color.FromArgb(11, 11, 16); // Slightly darker than main form
                this.DoubleBuffered = true;
                this.TopMost = true;
                
                // Rounded Corners
                this.Region = Region.FromHrgn(CreateRoundRectRgn(0, 0, this.Width, this.Height, 20, 20));
                
                timer = new System.Windows.Forms.Timer();
                timer.Interval = 30; // Fast refresh for smooth animation
                timer.Tick += Timer_Tick;
                timer.Start();
            }

            private void Timer_Tick(object sender, EventArgs e)
            {
                progress++;
                if (progress <= 100)
                {
                    // Update status text based on progress
                    int stepIndex = (progress / 20) % loadingSteps.Length;
                    if (stepIndex < loadingSteps.Length) statusText = loadingSteps[stepIndex];
                    this.Invalidate();
                }
                else
                {
                    timer.Stop();
                    this.Close();
                }
            }

            protected override void OnPaint(PaintEventArgs e)
            {
                base.OnPaint(e);
                Graphics g = e.Graphics;
                g.SmoothingMode = SmoothingMode.AntiAlias;
                g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;

                int cx = this.Width / 2;
                int cy = this.Height / 2;

                // Draw Background Elements (Subtle Grid or Glow)
                // Draw a subtle radial gradient at the center
                using (GraphicsPath path = new GraphicsPath())
                {
                    path.AddEllipse(cx - 200, cy - 200, 400, 400);
                    using (PathGradientBrush brush = new PathGradientBrush(path))
                    {
                        brush.CenterColor = Color.FromArgb(20, 0, 122, 204); // Very faint blue
                        brush.SurroundColors = new Color[] { Color.Transparent };
                        g.FillPath(brush, path);
                    }
                }

                // Draw Logo (Text based for now, replacing SVG)
                // "Smart" in White/Gray, "Shop" in Blue
                Font fontLarge = new Font("Segoe UI Light", 48);
                Font fontBold = new Font("Segoe UI", 48, FontStyle.Bold);
                
                string t1 = "Smart";
                string t2 = "Shop";
                
                Size s1 = TextRenderer.MeasureText(t1, fontLarge);
                Size s2 = TextRenderer.MeasureText(t2, fontBold);
                
                int totalWidth = s1.Width + s2.Width - 20; // -20 for spacing adjustment
                int startX = cx - (totalWidth / 2);
                int textY = cy - 60;

                g.DrawString(t1, fontLarge, new SolidBrush(Color.FromArgb(240, 240, 245)), startX, textY);
                g.DrawString(t2, fontBold, new SolidBrush(Color.FromArgb(0, 122, 204)), startX + s1.Width - 15, textY);

                // Tagline
                string tagline = "Powered by EagleShadow Technology";
                Font fontTag = new Font("Segoe UI", 9, FontStyle.Regular);
                Size sTag = TextRenderer.MeasureText(tagline, fontTag);
                g.DrawString(tagline, fontTag, new SolidBrush(Color.FromArgb(100, 100, 120)), cx - (sTag.Width / 2), textY + s1.Height + 5);

                // Progress Bar Background
                int barWidth = 300;
                int barHeight = 4;
                int barX = cx - (barWidth / 2);
                int barY = cy + 60;

                using (SolidBrush bgBrush = new SolidBrush(Color.FromArgb(30, 30, 35)))
                {
                    g.FillRectangle(bgBrush, barX, barY, barWidth, barHeight);
                }

                // Progress Bar Fill
                int fillWidth = (int)((progress / 100.0) * barWidth);
                using (LinearGradientBrush fillBrush = new LinearGradientBrush(new Point(barX, barY), new Point(barX + barWidth, barY), Color.FromArgb(0, 122, 204), Color.FromArgb(56, 182, 255)))
                {
                    g.FillRectangle(fillBrush, barX, barY, fillWidth, barHeight);
                }
                
                // Draw glow at the tip of the progress bar
                if (fillWidth > 0)
                {
                     // Simple glow circle
                     using (SolidBrush glowBrush = new SolidBrush(Color.FromArgb(100, 56, 182, 255)))
                     {
                         g.FillEllipse(glowBrush, barX + fillWidth - 6, barY - 4, 12, 12);
                     }
                     using (SolidBrush headBrush = new SolidBrush(Color.White))
                     {
                         g.FillEllipse(headBrush, barX + fillWidth - 2, barY, 4, 4);
                     }
                }

                // Status Text
                Font fontStatus = new Font("Consolas", 9);
                Size sStatus = TextRenderer.MeasureText(statusText, fontStatus);
                g.DrawString(statusText, fontStatus, new SolidBrush(Color.FromArgb(80, 80, 90)), cx - (sStatus.Width / 2), barY + 15);

                // Footer
                string copy = "© EagleShadow Technology";
                Font fontFooter = new Font("Segoe UI", 8);
                Size sFooter = TextRenderer.MeasureText(copy, fontFooter);
                g.DrawString(copy, fontFooter, new SolidBrush(Color.FromArgb(40, 40, 45)), cx - (sFooter.Width / 2), this.Height - 30);
            }
        }
        
        public class ContactForm : Form
        {
            [DllImport("Gdi32.dll", EntryPoint = "CreateRoundRectRgn")]
            private static extern IntPtr CreateRoundRectRgn(int nLeftRect, int nTopRect, int nRightRect, int nBottomRect, int nWidthEllipse, int nHeightEllipse);

            public ContactForm()
            {
                this.Text = "Contact Us";
                this.Size = new Size(500, 400);
                this.StartPosition = FormStartPosition.CenterParent;
                this.FormBorderStyle = FormBorderStyle.None; 
                this.BackColor = Color.FromArgb(22, 22, 25);
                this.ForeColor = Color.White;
                this.Region = Region.FromHrgn(CreateRoundRectRgn(0, 0, this.Width, this.Height, 15, 15));

                Label lblTitle = new Label { Text = "EagleShadow Technology", Font = new Font("Segoe UI Light", 18), ForeColor = Color.FromArgb(0, 122, 204), Dock = DockStyle.Top, Height = 80, TextAlign = ContentAlignment.BottomCenter };
                this.Controls.Add(lblTitle);
                
                Panel pnlInfo = new Panel { Dock = DockStyle.Fill, Padding = new Padding(40) };
                this.Controls.Add(pnlInfo);
                
                int y = 30;
                y = AddInfoRow(pnlInfo, "Email Support:", "support@eagleshadow.technology", y);
                y = AddInfoRow(pnlInfo, "WhatsApp:", "+212 700-979284 - HamzaSaadi", y);
                y = AddInfoRow(pnlInfo, "Website:", "eagleshadow.technology", y, "https://eagleshadow.technology/");
                
                Button btnClose = new Button { Text = "Close", Size = new Size(120, 40), Location = new Point((this.ClientSize.Width - 120) / 2, y + 20), BackColor = Color.FromArgb(0, 122, 204), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Font = new Font("Segoe UI", 10), Cursor = Cursors.Hand };
                btnClose.FlatAppearance.BorderSize = 0;
                btnClose.Click += (s, e) => { this.Close(); };
                pnlInfo.Controls.Add(btnClose);
                pnlInfo.BringToFront();
            }
            
            private int AddInfoRow(Panel pnl, string label, string value, int y)
            {
                pnl.Controls.Add(new Label { Text = label, Font = new Font("Segoe UI", 9, FontStyle.Bold), ForeColor = Color.FromArgb(140, 140, 140), AutoSize = true, Location = new Point(50, y) });
                pnl.Controls.Add(new Label { Text = value, Font = new Font("Segoe UI", 11), ForeColor = Color.White, AutoSize = true, Location = new Point(50, y + 20) });
                return y + 60;
            }

            private int AddInfoRow(Panel pnl, string label, string value, int y, string url)
            {
                pnl.Controls.Add(new Label { Text = label, Font = new Font("Segoe UI", 9, FontStyle.Bold), ForeColor = Color.FromArgb(140, 140, 140), AutoSize = true, Location = new Point(50, y) });
                
                Label lblLink = new Label { Text = value, Font = new Font("Segoe UI", 11, FontStyle.Underline), ForeColor = Color.FromArgb(0, 122, 204), AutoSize = true, Location = new Point(50, y + 20), Cursor = Cursors.Hand };
                lblLink.Click += (s, e) => { try { Process.Start(url); } catch { } };
                pnl.Controls.Add(lblLink);
                
                return y + 60;
            }
        }
        
        public class DbQueryForm : Form
        {
            private TextBox txtQuery;
            private Button btnExecute;
            private RichTextBox txtOutput;
            private CheckBox chkUseDb;
            private string appDir;
            private int dbPort;

            public DbQueryForm(string appDir, int dbPort) { this.appDir = appDir; this.dbPort = dbPort; InitializeComponent(); }

            private void InitializeComponent()
            {
                this.Text = "Database Console";
                this.Size = new Size(700, 500);
                this.StartPosition = FormStartPosition.CenterScreen;
                this.Icon = SystemIcons.Application;
                this.BackColor = Color.FromArgb(22, 22, 25);
                this.ForeColor = Color.White;

                this.Controls.Add(new Label() { Text = "SQL Query:", Location = new Point(10, 10), AutoSize = true, Font = new Font("Segoe UI", 9) });
                chkUseDb = new CheckBox() { Text = "Use 'smart_shop' database", Location = new Point(100, 9), AutoSize = true, Checked = true, Font = new Font("Segoe UI", 9) };
                this.Controls.Add(chkUseDb);

                txtQuery = new TextBox { Multiline = true, Location = new Point(10, 35), Size = new Size(660, 100), ScrollBars = ScrollBars.Vertical, Font = new Font("Consolas", 10), BackColor = Color.FromArgb(13, 13, 15), ForeColor = Color.White, BorderStyle = BorderStyle.FixedSingle, Text = "SHOW TABLES;" };
                this.Controls.Add(txtQuery);

                btnExecute = new Button { Text = "Execute (Ctrl+Enter)", Location = new Point(10, 145), Size = new Size(150, 35), FlatStyle = FlatStyle.Flat, BackColor = Color.FromArgb(0, 122, 204), ForeColor = Color.White, Cursor = Cursors.Hand };
                btnExecute.FlatAppearance.BorderSize = 0;
                btnExecute.Click += BtnExecute_Click;
                this.Controls.Add(btnExecute);

                this.Controls.Add(new Label() { Text = "Result:", Location = new Point(10, 190), AutoSize = true, Font = new Font("Segoe UI", 9) });

                txtOutput = new RichTextBox { Location = new Point(10, 215), Size = new Size(660, 230), ReadOnly = true, Font = new Font("Consolas", 9), BackColor = Color.FromArgb(13, 13, 15), ForeColor = Color.White, BorderStyle = BorderStyle.None, WordWrap = false, ScrollBars = RichTextBoxScrollBars.Both };
                this.Controls.Add(txtOutput);

                txtQuery.KeyDown += (s, e) => { if (e.Control && e.KeyCode == Keys.Enter) BtnExecute_Click(null, null); };
            }

            private void BtnExecute_Click(object sender, EventArgs e)
            {
                txtOutput.Clear();
                string query = txtQuery.Text.Trim();
                if (string.IsNullOrEmpty(query)) return;

                string binMySQL = Path.Combine(appDir, "bin", "mysql", "bin", "mysql.exe");
                if (!File.Exists(binMySQL)) { txtOutput.Text = "Error: mysql.exe not found at " + binMySQL; return; }

                string dbArg = chkUseDb.Checked ? "smart_shop" : "";
                string args = String.Format("-h 127.0.0.1 -P {0} -u root {1} -t --default-character-set=utf8", dbPort, dbArg);

                try
                {
                    ProcessStartInfo psi = new ProcessStartInfo { FileName = binMySQL, Arguments = args, UseShellExecute = false, RedirectStandardInput = true, RedirectStandardOutput = true, RedirectStandardError = true, CreateNoWindow = true, StandardOutputEncoding = System.Text.Encoding.UTF8 };
                    using (Process p = Process.Start(psi))
                    {
                        p.StandardInput.WriteLine(query); p.StandardInput.Close();
                        string output = p.StandardOutput.ReadToEnd(); string error = p.StandardError.ReadToEnd();
                        p.WaitForExit();
                        if (!string.IsNullOrEmpty(error)) { if (!error.Trim().StartsWith("mysql: [Warning]")) { txtOutput.SelectionColor = Color.Red; txtOutput.AppendText("STDERR:\n" + error + "\n"); } }
                        txtOutput.SelectionColor = Color.White; txtOutput.AppendText(output);
                    }
                }
                catch (Exception ex) { txtOutput.SelectionColor = Color.Red; txtOutput.AppendText("Exception: " + ex.Message); }
            }
        }
    }
}