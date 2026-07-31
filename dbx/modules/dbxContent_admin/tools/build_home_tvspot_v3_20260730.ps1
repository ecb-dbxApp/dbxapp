param(
    [string]$FfmpegPath = '',
    [string]$NodePath = '',
    [switch]$ReusePreparedFrames
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..\..')).Path
$workspaceParent = Split-Path -Parent $repoRoot

if ($FfmpegPath -eq '') {
    $bundledFfmpeg = Join-Path $workspaceParent 'dbxapp-cms-tutorials\node_modules\ffmpeg-static\ffmpeg.exe'
    if (Test-Path -LiteralPath $bundledFfmpeg) {
        $FfmpegPath = $bundledFfmpeg
    } else {
        $FfmpegPath = (Get-Command ffmpeg -ErrorAction Stop).Source
    }
}
if ($NodePath -eq '') {
    $NodePath = (Get-Command node -ErrorAction Stop).Source
}
if (-not (Test-Path -LiteralPath $FfmpegPath -PathType Leaf)) {
    throw "FFmpeg wurde nicht gefunden: $FfmpegPath"
}
if (-not (Test-Path -LiteralPath $NodePath -PathType Leaf)) {
    throw "Node.js wurde nicht gefunden: $NodePath"
}

function Format-Decimal([double]$Value) {
    return [string]::Format(
        [Globalization.CultureInfo]::InvariantCulture,
        '{0:0.000}',
        $Value
    )
}

function Escape-DrawText([string]$Value) {
    return $Value.Replace('\', '\\').Replace("'", "\'").Replace(':', '\:')
}

$width = 1024
$height = 576
$workDir = Join-Path $repoRoot 'files\tmp\dbxapp-tvspot-20260730-v3'
$videoDir = Join-Path $repoRoot 'files\media\video'
$imageDir = Join-Path $repoRoot 'files\media\img\images'
$heroDir = Join-Path $repoRoot 'files\media\img\hero'
$galleryDir = Join-Path $repoRoot 'files\media\img\gallery'

$videoFile = Join-Path $videoDir 'dbxapp-tvspot-20260730-v3.mp4'
$posterFile = Join-Path $imageDir 'dbxapp-tvspot-poster-20260730-v3.webp'
$musicFile = Join-Path $workDir 'dbxapp-electro-disco-original-126bpm.wav'
$musicGenerator = Join-Path $PSScriptRoot 'generate_home_tvspot_music_v3_20260730.mjs'

$originalLogo = Join-Path $imageDir 'dbxapp-original-logo-20260730.png'
$shopSource = Join-Path $imageDir 'dbxapp-tvspot-source-shop-active-20260730.png'
$healthSource = Join-Path $imageDir 'dbxapp-tvspot-source-health-100-20260730.png'
$cmsSource = Join-Path $galleryDir 'tutorial-cms-uebersicht.webp'
$databaseSource = Join-Path $galleryDir 'tutorial-admin-dashboard-10-datenbanken.webp'
$workflowSource = Join-Path $galleryDir 'tutorial-workflow-nutzen-01-start.webp'

$mobileHero = Join-Path $heroDir 'dbxapp-info-mobile.webp'
$desktopHero = Join-Path $heroDir 'dbxapp-info-desktop.webp'
$kiHero = Join-Path $heroDir 'dbxapp-info-ki.webp'
$cmsHero = Join-Path $heroDir 'dbxapp-info-cms.webp'
$shopHero = Join-Path $heroDir 'dbxapp-info-shop.webp'
$systemHero = Join-Path $heroDir 'dbxapp-dashboard-systemblick-web.webp'
$platformHero = Join-Path $heroDir 'dbxapp-platform-hero-20260728.webp'
$devicesHero = Join-Path $heroDir 'dbxapp-desktop-mobile-cloud-hero.webp'
$modularHero = Join-Path $imageDir 'dbxapp-modular-wachsen-20260728.webp'
$brandHero = Join-Path $imageDir 'dbxapp-eine-plattform-alle-moeglichkeiten.webp'

New-Item -ItemType Directory -Force -Path $workDir, $videoDir, $imageDir | Out-Null

$requiredSources = @(
    $originalLogo,
    $shopSource,
    $healthSource,
    $cmsSource,
    $databaseSource,
    $workflowSource,
    $mobileHero,
    $desktopHero,
    $kiHero,
    $cmsHero,
    $shopHero,
    $systemHero,
    $platformHero,
    $devicesHero,
    $modularHero,
    $brandHero,
    $musicGenerator
)
foreach ($source in $requiredSources) {
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw "Szenenquelle fehlt: $source"
    }
}

& $NodePath $musicGenerator $musicFile
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $musicFile -PathType Leaf)) {
    throw 'Der originale Electro-Disco-Track konnte nicht erzeugt werden.'
}

$fontBold = 'C\:/Windows/Fonts/arialbd.ttf'
$fontRegular = 'C\:/Windows/Fonts/arial.ttf'
$introCard = Join-Path $workDir 'source-intro-card.png'
$solutionCard = Join-Path $workDir 'source-solution-card.png'

$introFilter = @(
    "drawgrid=width=58:height=58:thickness=1:color=0x38bdf8@0.08"
    "drawbox=x=70:y=96:w=884:h=384:color=0x0b2d5c@0.82:t=fill"
    "drawbox=x=70:y=96:w=10:h=384:color=0x22d3ee@0.96:t=fill"
    "drawtext=fontfile='$fontBold':text='MEHR KANÄLE.':fontcolor=white:fontsize=61:x=118:y=150"
    "drawtext=fontfile='$fontBold':text='EIN SYSTEM.':fontcolor=0x7dd3fc:fontsize=61:x=118:y=240"
    "drawtext=fontfile='$fontRegular':text='HANDY  ·  DESKTOP  ·  KI  ·  CMS  ·  SHOP  ·  DB':fontcolor=0xdbeafe:fontsize=21:x=118:y=380"
) -join ','

& $FfmpegPath -hide_banner -loglevel error -y `
    -f lavfi -i 'color=c=0x061b3c:s=1024x576:d=1:r=30' `
    -vf $introFilter -frames:v 1 $introCard

$solutionFilter = @(
    "drawgrid=width=58:height=58:thickness=1:color=0x38bdf8@0.08"
    "drawbox=x=92:y=126:w=840:h=322:color=0x0b2d5c@0.84:t=fill"
    "drawbox=x=92:y=126:w=840:h=8:color=0x22d3ee@0.96:t=fill"
    "drawtext=fontfile='$fontRegular':text='VIELE AUFGABEN. EIN GEMEINSAMER KERN.':fontcolor=0x93c5fd:fontsize=24:x=(w-text_w)/2:y=200"
    "drawtext=fontfile='$fontBold':text='DIE LÖSUNG':fontcolor=white:fontsize=72:x=(w-text_w)/2:y=276"
    "drawtext=fontfile='$fontBold':text='dbxapp':fontcolor=0x7dd3fc:fontsize=38:x=(w-text_w)/2:y=374"
) -join ','

& $FfmpegPath -hide_banner -loglevel error -y `
    -f lavfi -i 'color=c=0x061b3c:s=1024x576:d=1:r=30' `
    -vf $solutionFilter -frames:v 1 $solutionCard

foreach ($card in @($introCard, $solutionCard)) {
    if (-not (Test-Path -LiteralPath $card -PathType Leaf)) {
        throw "Titelkarte fehlt: $card"
    }
}

$scenes = @(
    @{ Kind = 'card'; File = $introCard; Duration = 1.10; Title = ''; Motion = 0 },
    @{ Kind = 'hero'; File = $mobileHero; Duration = 1.40; Title = 'HANDY'; Motion = 1 },
    @{ Kind = 'hero'; File = $mobileHero; Duration = 1.00; Title = 'ÜBERALL DABEI'; Motion = 2 },
    @{ Kind = 'hero'; File = $desktopHero; Duration = 1.40; Title = 'DESKTOP'; Motion = 3 },
    @{ Kind = 'hero'; File = $desktopHero; Duration = 1.00; Title = 'VOLLER ÜBERBLICK'; Motion = 2 },
    @{ Kind = 'hero'; File = $kiHero; Duration = 1.40; Title = 'KI'; Motion = 1 },
    @{ Kind = 'hero'; File = $kiHero; Duration = 1.00; Title = 'IDEEN WERDEN PROZESSE'; Motion = 3 },
    @{ Kind = 'hero'; File = $cmsHero; Duration = 1.20; Title = 'CMS'; Motion = 1 },
    @{ Kind = 'panel'; File = $cmsSource; Background = $cmsHero; Duration = 1.70; Eyebrow = 'CMS'; Title = 'INHALTE DIREKT'; Motion = 5 },
    @{ Kind = 'hero'; File = $shopHero; Duration = 1.20; Title = 'SHOP'; Motion = 3 },
    @{ Kind = 'panel'; File = $shopSource; Background = $shopHero; Duration = 1.70; Eyebrow = 'SHOP'; Title = 'AKTIV VERKAUFEN'; Motion = 5 },
    @{ Kind = 'hero'; File = $systemHero; Duration = 1.20; Title = 'DATENBANK'; Motion = 1 },
    @{ Kind = 'panel'; File = $databaseSource; Background = $systemHero; Duration = 1.60; Eyebrow = 'DATENBANK'; Title = 'DATEN KLAR'; Motion = 5 },
    @{ Kind = 'panel'; File = $healthSource; Background = $systemHero; Duration = 1.65; Eyebrow = 'SYSTEM HEALTH'; Title = '100 %'; Motion = 5 },
    @{ Kind = 'panel'; File = $workflowSource; Background = $platformHero; Duration = 1.45; Eyebrow = 'WORKFLOW'; Title = 'ALLES VERBUNDEN'; Motion = 5 },
    @{ Kind = 'hero'; File = $modularHero; Duration = 1.35; Title = 'MODULAR WACHSEN'; Motion = 3 },
    @{ Kind = 'hero'; File = $devicesHero; Duration = 1.35; Title = 'WEB. DESKTOP. MOBIL.'; Motion = 1 },
    @{ Kind = 'card'; File = $solutionCard; Duration = 1.10; Title = ''; Motion = 0 },
    @{ Kind = 'hero'; File = $brandHero; Duration = 2.20; Title = ''; Motion = 0 },
    @{ Kind = 'logo'; File = $originalLogo; Duration = 4.00; Title = ''; Motion = 4 }
)

$preparedFrames = @()
for ($index = 0; $index -lt $scenes.Count; $index++) {
    $scene = $scenes[$index]
    $target = Join-Path $workDir ('scene-{0:D2}.png' -f ($index + 1))
    $titleFilter = ''

    if ($ReusePreparedFrames -and (Test-Path -LiteralPath $target -PathType Leaf)) {
        $preparedFrames += $target
        continue
    }

    if (([string]$scene.Title) -ne '' -and $scene.Kind -ne 'panel') {
        $safeTitle = Escape-DrawText ([string]$scene.Title)
        $titleFilter = ",drawtext=fontfile='$fontBold':text='$safeTitle':fontcolor=white:fontsize=35:x=40:y=492:box=1:boxcolor=0x061b3c@0.88:boxborderw=16"
    }

    switch ($scene.Kind) {
        'panel' {
            $safeEyebrow = Escape-DrawText ([string]$scene.Eyebrow)
            $safePanelTitle = Escape-DrawText ([string]$scene.Title)
            $panelFilter = "[0:v]scale=1024:576:force_original_aspect_ratio=increase," +
                "crop=1024:576,eq=brightness=-0.12:saturation=1.12,gblur=sigma=2.4," +
                "drawbox=x=0:y=0:w=iw:h=ih:color=0x03152f@0.22:t=fill," +
                "drawbox=x=278:y=86:w=714:h=412:color=0x000000@0.46:t=fill[base];" +
                "[1:v]scale=686:382:force_original_aspect_ratio=decrease," +
                "pad=706:402:(ow-iw)/2:(oh-ih)/2:color=0x071a36," +
                "drawbox=x=0:y=0:w=iw:h=ih:color=0x38bdf8@0.9:t=3[panel];" +
                "[base][panel]overlay=262:72," +
                "drawbox=x=36:y=190:w=206:h=186:color=0x061b3c@0.86:t=fill," +
                "drawbox=x=36:y=190:w=7:h=186:color=0x22d3ee@0.96:t=fill," +
                "drawtext=fontfile='$fontRegular':text='$safeEyebrow':fontcolor=0x7dd3fc:fontsize=20:x=62:y=228:expansion=none," +
                "drawtext=fontfile='$fontBold':text='$safePanelTitle':fontcolor=white:fontsize=30:x=62:y=280:expansion=none," +
                "format=rgb24[out]"

            & $FfmpegPath -hide_banner -loglevel error -y `
                -i $scene.Background -i $scene.File `
                -filter_complex $panelFilter `
                -map '[out]' -frames:v 1 $target
        }
        'logo' {
            $logoFilter = "scale=1024:576:force_original_aspect_ratio=increase," +
                "crop=1024:576,eq=saturation=1.03,format=rgb24"
            & $FfmpegPath -hide_banner -loglevel error -y `
                -i $scene.File -vf $logoFilter -frames:v 1 $target
        }
        'card' {
            & $FfmpegPath -hide_banner -loglevel error -y `
                -i $scene.File `
                -vf 'scale=1024:576:flags=lanczos,format=rgb24' `
                -frames:v 1 $target
        }
        default {
            $heroFilter = "scale=1024:576:force_original_aspect_ratio=increase," +
                "crop=1024:576,eq=contrast=1.04:saturation=1.10," +
                "drawbox=x=0:y=0:w=iw:h=ih:color=0x03152f@0.04:t=fill" +
                $titleFilter +
                ",format=rgb24"
            & $FfmpegPath -hide_banner -loglevel error -y `
                -i $scene.File -vf $heroFilter -frames:v 1 $target
        }
    }

    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $target -PathType Leaf)) {
        throw "Szenenbild konnte nicht vorbereitet werden: $($scene.File)"
    }
    $preparedFrames += $target
}

$ffmpegArguments = @('-hide_banner', '-loglevel', 'error', '-y')
for ($index = 0; $index -lt $preparedFrames.Count; $index++) {
    $duration = Format-Decimal $scenes[$index].Duration
    $ffmpegArguments += @('-loop', '1', '-framerate', '30', '-t', $duration, '-i', $preparedFrames[$index])
}
$ffmpegArguments += @('-i', $musicFile)

$filters = @()
for ($index = 0; $index -lt $preparedFrames.Count; $index++) {
    $duration = Format-Decimal $scenes[$index].Duration
    switch ([int]$scenes[$index].Motion) {
        1 {
            $motion = "zoompan=z='min(zoom+0.0062,1.30)':x='(iw-iw/zoom)*min(on/48,1)':y='ih/2-(ih/zoom/2)':d=1:s=1024x576:fps=30"
        }
        2 {
            $motion = "zoompan=z='if(eq(on,0),1.28,max(zoom-0.0072,1.03))':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1024x576:fps=30"
        }
        3 {
            $motion = "zoompan=z='min(zoom+0.0068,1.32)':x='(iw-iw/zoom)*max(0,1-on/52)':y='ih/2-(ih/zoom/2)':d=1:s=1024x576:fps=30"
        }
        4 {
            $motion = "zoompan=z='min(zoom+0.00085,1.10)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1024x576:fps=30"
        }
        5 {
            $motion = "zoompan=z='min(zoom+0.0042,1.20)':x='(iw-iw/zoom)*0.68':y='ih/2-(ih/zoom/2)':d=1:s=1024x576:fps=30"
        }
        default {
            $motion = "zoompan=z='min(zoom+0.0055,1.25)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1024x576:fps=30"
        }
    }
    $filters += "[$index`:v]$motion,trim=duration=$duration,setpts=PTS-STARTPTS,format=yuv420p[v$index]"
}

$concatInputs = ''
for ($index = 0; $index -lt $preparedFrames.Count; $index++) {
    $concatInputs += "[v$index]"
}
$filters += "$concatInputs" + "concat=n=$($preparedFrames.Count):v=1:a=0,trim=duration=30,setpts=PTS-STARTPTS," +
    "vignette=PI/7,eq=contrast=1.04:saturation=1.07,unsharp=5`:5`:0.28,format=yuv420p[outv]"

$audioInput = $preparedFrames.Count
$filters += "[$audioInput`:a]atrim=duration=30,asetpts=PTS-STARTPTS," +
    "afade=t=out`:st=29.30`:d=0.70,loudnorm=I=-13.0`:LRA=7`:TP=-1.1[outa]"
$filterComplex = $filters -join ';'

$ffmpegArguments += @(
    '-filter_complex', $filterComplex,
    '-map', '[outv]',
    '-map', '[outa]',
    '-t', '30',
    '-c:v', 'libx264',
    '-preset', 'medium',
    '-crf', '19',
    '-maxrate', '1900k',
    '-bufsize', '3800k',
    '-r', '30',
    '-pix_fmt', 'yuv420p',
    '-profile:v', 'high',
    '-level', '4.0',
    '-c:a', 'aac',
    '-b:a', '192k',
    '-ar', '48000',
    '-movflags', '+faststart',
    '-shortest',
    $videoFile
)

& $FfmpegPath @ffmpegArguments
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $videoFile -PathType Leaf)) {
    throw 'Die dritte, bewegtere TV-Spot-Fassung konnte nicht gerendert werden.'
}

& $FfmpegPath -hide_banner -loglevel error -y `
    -ss 28.90 -i $videoFile `
    -frames:v 1 -vf 'scale=1024:576:flags=lanczos' `
    -c:v libwebp -quality 92 $posterFile

if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $posterFile -PathType Leaf)) {
    throw 'Das Poster der bewegteren Fassung konnte nicht erstellt werden.'
}

$previewTimes = @(2.0, 10.2, 13.2, 17.5, 29.0)
for ($index = 0; $index -lt $previewTimes.Count; $index++) {
    $preview = Join-Path $workDir ('preview-{0:D2}.png' -f ($index + 1))
    & $FfmpegPath -hide_banner -loglevel error -y `
        -ss $previewTimes[$index] -i $videoFile `
        -frames:v 1 -vf 'scale=1024:576:flags=lanczos' $preview
    if ($LASTEXITCODE -ne 0) {
        throw "Vorschau $($index + 1) konnte nicht erstellt werden."
    }
}

Write-Output "Video: $videoFile"
Write-Output "Poster: $posterFile"
Write-Output "Originaltrack: $musicFile"
