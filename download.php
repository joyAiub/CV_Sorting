<?php
if (!isset($_GET['file'])) {
    die("Invalid request.");
}

// Security: Use basename to prevent directory traversal
$file = basename($_GET['file']);
$filepath = __DIR__ . '/uploads/exports/' . $file;

if (!file_exists($filepath)) {
    die("File not found or has been removed from the server.");
}

$file_url = 'uploads/exports/' . rawurlencode($file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloading Candidate Documents...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div id="downloadContainer" class="bg-white p-10 rounded-2xl shadow-xl border border-slate-100 max-w-md w-full text-center transition-all duration-500">
        
        <!-- Pre-Download State -->
        <div id="state-counting">
            <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="material-icons text-4xl text-indigo-600 animate-bounce">file_download</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">Starting in <span id="countdown">3</span>...</h2>
            <p class="text-slate-500 mb-8 text-sm">Please wait while we prepare your file. It will download automatically shortly.</p>
            
            <a href="<?php echo htmlspecialchars($file_url); ?>" download class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-colors w-full shadow-md shadow-indigo-200">
                <span class="material-icons text-xl">get_app</span> Download Manually
            </a>
        </div>

        <!-- Post-Download State (Hidden initially) -->
        <div id="state-done" class="hidden">
            <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="material-icons text-4xl text-emerald-500">check_circle</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3">Download Initiated!</h2>
            <p class="text-slate-500 mb-6 text-sm">Your file should be downloading now. Once it completes, you can safely close this tab.</p>
            
            <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 mb-6">
                <p class="text-xs text-slate-400">Didn't get the file?</p>
                <a href="<?php echo htmlspecialchars($file_url); ?>" download class="text-sm font-bold text-indigo-600 hover:text-indigo-800 transition-colors mt-1 inline-block">
                    Click here to try again
                </a>
            </div>

            <button onclick="window.close();" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl transition-colors w-full shadow-md">
                <span class="material-icons text-xl">close</span> Close Tab
            </button>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let count = 3;
            const countdownEl = document.getElementById('countdown');
            const stateCounting = document.getElementById('state-counting');
            const stateDone = document.getElementById('state-done');
            
            const timer = setInterval(() => {
                count--;
                countdownEl.textContent = count;
                
                if (count <= 0) {
                    clearInterval(timer);
                    // Trigger download
                    const link = document.createElement('a');
                    link.href = '<?php echo addslashes($file_url); ?>';
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Swap UI
                    stateCounting.classList.add('hidden');
                    stateDone.classList.remove('hidden');
                }
            }, 1000);
        });
    </script>
</body>
</html>
