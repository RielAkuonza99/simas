import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const featurePaths = {
  'Notifikasi': [
    'app/Controllers/NotificationController.php',
    'app/Views/notifications/',
  ],
  'Feedback': [
    'app/Controllers/DeveloperFeedbackController.php',
    'app/Models/DeveloperFeedbackModel.php',
    'app/Services/BrevoFeedbackMailer.php',
    'app/Services/FeedbackUploadService.php',
    'app/Views/faq/index.php',
  ],
  'Developer tools': [
    'app/Controllers/SettingsController.php',
    'app/Models/SystemMetricsModel.php',
    'app/Views/settings/index.php',
  ],
  'Integrasi dan sinkronisasi': [
    'app/Controllers/ArchiveSyncController.php',
    'app/Models/ArchiveSyncModel.php',
    'app/Services/ArchiveSyncService.php',
    'app/Services/SupabaseArchiveClient.php',
    'app/Views/archive-sync/',
    'bin/queue-archive-backfill.php',
    'bin/sync-archive.php',
  ],
  'Log Sesi (Perangkat dan sesi)': [
    'app/Controllers/SecurityController.php',
    'app/Models/ActivityModel.php',
    'app/Models/AuthSessionModel.php',
    'app/Views/security/',
    'app/Views/settings/index.php',
  ],
  'Panduan': [
    'app/Controllers/FaqController.php',
    'app/Views/faq/',
  ],
  'Account': [
    'app/Controllers/UserController.php',
    'app/Models/UserModel.php',
    'app/Services/UserAdminService.php',
    'app/Views/users/',
  ],
  'Surat Masuk': [
    'app/Controllers/SuratController.php',
    'app/Models/SuratModel.php',
    'app/Services/SuratService.php',
    'app/Services/SuratExportFormatter.php',
    'app/Services/SimpleXlsxWriter.php',
    'app/Views/surat/',
    'app/Views/disposition-history/',
  ],
  'Login': [
    'app/Controllers/AuthController.php',
    'app/Middleware/AuthMiddleware.php',
    'app/Models/AuthSessionModel.php',
    'app/Security/LoginRateLimiter.php',
    'app/Services/AuthService.php',
    'app/Views/auth/',
  ],
};

const componentPaths = {
  'Metadata eksternal': featurePaths['Integrasi dan sinkronisasi'],
  'Data Internal': featurePaths['Surat Masuk'],
};
const countCommits = (paths = []) => Number(execFileSync(
  'git',
  ['rev-list', '--count', 'HEAD', ...(paths.length > 0 ? ['--', ...paths] : [])],
  { encoding: 'utf8' },
).trim());

const configPath = new URL('../config/updates.json', import.meta.url);
const updates = JSON.parse(readFileSync(configPath, 'utf8'));
const websiteUpdates = countCommits();
updates.website.git_updates = websiteUpdates;
if (updates.website.version === 'Belum ditetapkan') {
  updates.website.version = `V${websiteUpdates}`;
}
if (updates.website.release === 'Belum ditetapkan') {
  updates.website.release = `Rilis Git ke-${websiteUpdates}`;
}

for (const feature of updates.features) {
  if (feature.name === 'Website Update') {
    feature.git_updates = websiteUpdates;
    if (feature.version === 'Belum ditetapkan') {
      feature.version = `V${websiteUpdates}`;
    }
    if (feature.release === 'Belum ditetapkan') {
      feature.release = `Rilis Git ke-${websiteUpdates}`;
    }
  } else {
    const paths = featurePaths[feature.name];
    if (!paths) {
      throw new Error(`Pemetaan path belum tersedia untuk fitur: ${feature.name}`);
    }
    feature.git_updates = countCommits(paths);
    if (feature.version === 'Belum ditetapkan') {
      feature.version = `V${feature.git_updates}`;
    }
  }

  for (const component of feature.components ?? []) {
    const paths = componentPaths[component.name];
    if (!paths) {
      throw new Error(`Pemetaan path belum tersedia untuk komponen: ${component.name}`);
    }
    component.git_updates = countCommits(paths);
  }
}

if (process.argv.includes('--apply')) {
  writeFileSync(configPath, `${JSON.stringify(updates, null, 2)}\n`);
}

console.log(`Website Update: V${websiteUpdates}`);
for (const feature of updates.features) {
  console.log(`${feature.name}: ${feature.version} (revisi Git ${feature.git_updates})`);
}