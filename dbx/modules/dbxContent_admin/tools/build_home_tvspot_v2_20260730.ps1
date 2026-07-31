param(
    [string]$FfmpegPath = '',
    [string]$NodePath = ''
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

$workDir = Join-Path $repoRoot 'files\tmp\dbxapp-tvspot-20260730-v2'
$videoDir = Join-Path $repoRoot 'files\media\video'
$posterDir = Join-Path $repoRoot 'files\media\img\images'
$videoFile = Join-Path $videoDir 'dbxapp-tvspot-20260730-v2.mp4'
$posterFile = Join-Path $posterDir 'dbxapp-tvspot-poster-20260730-v2.webp'
$originalLogo = Join-Path $posterDir 'dbxapp-original-logo-20260730.png'
$shopSource = Join-Path $posterDir 'dbxapp-tvspot-source-shop-active-20260730.png'
$healthSource = Join-Path $posterDir 'dbxapp-tvspot-source-health-100-20260730.png'
$musicFile = Join-Path $workDir 'dbxapp-positive-original-128bpm.wav'
$musicGenerator = Join-Path $PSScriptRoot 'generate_home_tvspot_music_20260730.mjs'

New-Item -ItemType Directory -Force -Path $workDir, $videoDir, $posterDir | Out-Null

$requiredCaptures = @(
    $shopSource,
    $healthSource
)
foreach ($capture in $requiredCaptures) {
    if (-not (Test-Path -LiteralPath $capture -PathType Leaf)) {
        throw "Aktuelle Filmszene fehlt: $capture"
    }
}

& $NodePath $musicGenerator $musicFile
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $musicFile -PathType Leaf)) {
    throw 'Der positive Originaltrack konnte nicht erzeugt werden.'
}

$fontBold = 'C\:/Windows/Fonts/arialbd.ttf'
$fontRegular = 'C\:/Windows/Fonts/arial.ttf'

$introCard = Join-Path $workDir 'source-intro-card.png'
$solutionCard = Join-Path $workDir 'source-solution-card.png'

$introFilter = @(
    "drawgrid=width=72:height=72:thickness=1:color=0x38bdf8@0.09"
    "drawbox=x=110:y=150:w=1060:h=420:color=0x0b2d5c@0.78:t=fill"
    "drawbox=x=110:y=150:w=12:h=420:color=0x22d3ee@0.95:t=fill"
    "drawtext=fontfile='$fontBold':text='MEHR KANÄLE.':fontcolor=white:fontsize=76:x=170:y=225"
    "drawtext=fontfile='$fontBold':text='EIN SYSTEM.':fontcolor=0x7dd3fc:fontsize=76:x=170:y=335"
    "drawtext=fontfile='$fontRegular':text='HANDY  ·  DESKTOP  ·  KI  ·  CMS  ·  SHOP  ·  DB':fontcolor=0xdbeafe:fontsize=27:x=170:y=485"
) -join ','

& $FfmpegPath -hide_banner -loglevel error -y `
    -f lavfi -i 'color=c=0x061b3c:s=1280x720:d=1:r=30' `
    -vf $introFilter -frames:v 1 $introCard

$solutionFilter = @(
    "drawgrid=width=72:height=72:thickness=1:color=0x38bdf8@0.09"
    "drawbox=x=140:y=190:w=1000:h=340:color=0x0b2d5c@0.82:t=fill"
    "drawbox=x=140:y=190:w=1000:h=10:color=0x22d3ee@0.95:t=fill"
    "drawtext=fontfile='$fontRegular':text='VIELE AUFGABEN. EIN GEMEINSAMER KERN.':fontcolor=0x93c5fd:fontsize=31:x=(w-text_w)/2:y=270"
    "drawtext=fontfile='$fontBold':text='DIE LÖSUNG':fontcolor=white:fontsize=92:x=(w-text_w)/2:y=355"
) -join ','

& $FfmpegPath -hide_banner -loglevel error -y `
    -f lavfi -i 'color=c=0x061b3c:s=1280x720:d=1:r=30' `
    -vf $solutionFilter -frames:v 1 $solutionCard

foreach ($card in @($introCard, $solutionCard, $originalLogo)) {
    if (-not (Test-Path -LiteralPath $card -PathType Leaf)) {
        throw "Szenenquelle fehlt: $card"
    }
}

$scenes = @(
    @{ File = $introCard; Duration = 1.4; Title = ''; Motion = 0 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-mobile.webp'); Duration = 1.6; Title = 'HANDY'; Motion = 1 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-mobile.webp'); Duration = 1.3; Title = ''; Motion = 2 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-desktop.webp'); Duration = 1.6; Title = 'DESKTOP'; Motion = 1 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-desktop.webp'); Duration = 1.3; Title = ''; Motion = 2 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-ki.webp'); Duration = 1.6; Title = 'KI'; Motion = 0 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-cms.webp'); Duration = 1.5; Title = 'CMS'; Motion = 1 },
    @{ File = (Join-Path $repoRoot 'files\media\img\gallery\tutorial-cms-uebersicht.webp'); Duration = 1.8; Title = 'INHALTE. DIREKT.'; Motion = 2 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-info-shop.webp'); Duration = 1.5; Title = 'SHOP'; Motion = 1 },
    @{ File = $shopSource; Duration = 1.8; Title = 'AKTIVER SHOP'; Motion = 2 },
    @{ File = (Join-Path $repoRoot 'files\media\img\gallery\tutorial-admin-dashboard-10-datenbanken.webp'); Duration = 1.8; Title = 'DATENBANK'; Motion = 1 },
    @{ File = (Join-Path $repoRoot 'files\media\img\gallery\tutorial-admin-dashboard-10-datenbanken.webp'); Duration = 1.3; Title = 'DATEN. KLAR.'; Motion = 2 },
    @{ File = $healthSource; Duration = 1.8; Title = 'SYSTEM HEALTH: 100 %'; Motion = 0 },
    @{ File = (Join-Path $repoRoot 'files\media\img\gallery\tutorial-workflow-nutzen-01-start.webp'); Duration = 1.5; Title = 'WORKFLOWS'; Motion = 2 },
    @{ File = (Join-Path $repoRoot 'files\media\img\images\dbxapp-modular-wachsen-20260728.webp'); Duration = 1.7; Title = 'ALLES VERBUNDEN'; Motion = 0 },
    @{ File = (Join-Path $repoRoot 'files\media\img\hero\dbxapp-desktop-mobile-cloud-hero.webp'); Duration = 1.7; Title = 'WEB. DESKTOP. MOBIL.'; Motion = 1 },
    @{ File = $solutionCard; Duration = 1.3; Title = ''; Motion = 2 },
    @{ File = (Join-Path $repoRoot 'files\media\img\images\dbxapp-eine-plattform-alle-moeglichkeiten.webp'); Duration = 2.5; Title = ''; Motion = 0 },
    @{ File = $originalLogo; Duration = 3.0; Title = ''; Motion = 3 }
)

$preparedFrames = @()
for ($index = 0; $index -lt $scenes.Count; $index++) {
    $scene = $scenes[$index]
    if (-not (Test-Path -LiteralPath $scene.File -PathType Leaf)) {
        throw "Szenenbild fehlt: $($scene.File)"
    }

    $target = Join-Path $workDir ('scene-{0:D2}.png' -f ($index + 1))
    $titleFilter = ''
    if ($scene.Title -ne '') {
        $safeTitle = $scene.Title.Replace("'", "\'").Replace(':', '\:')
        $titleFilter = ",drawtext=fontfile='$fontBold':text='$safeTitle':fontcolor=white:fontsize=44:x=58:y=612:box=1:boxcolor=0x061b3c@0.90:boxborderw=20"
    }

    $isOriginalLogo = [string]::Equals(
        [string]$scene.File,
        [string]$originalLogo,
        [StringComparison]::OrdinalIgnoreCase
    )
    if ($isOriginalLogo) {
        $prepareFilter = "[0:v]split=2[bg][fg];" +
            "[bg]scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720," +
            "gblur=sigma=30,eq=brightness=-0.20:saturation=1.03[back];" +
            "[fg]scale=1280:720:force_original_aspect_ratio=decrease[front];" +
            "[back][front]overlay=(W-w)/2:(H-h)/2,format=rgb24[out]"
    } else {
        $prepareFilter = "[0:v]split=2[bg][fg];" +
            "[bg]scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720," +
            "gblur=sigma=27,eq=brightness=-0.18:saturation=0.94[back];" +
            "[fg]scale=1280:720:force_original_aspect_ratio=decrease[front];" +
            "[back][front]overlay=(W-w)/2:(H-h)/2," +
            "drawbox=x=0:y=0:w=iw:h=ih:color=0x03152f@0.05:t=fill" +
            $titleFilter +
            ",format=rgb24[out]"
    }

    & $FfmpegPath -hide_banner -loglevel error -y `
        -i $scene.File `
        -filter_complex $prepareFilter `
        -map '[out]' -frames:v 1 $target

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
            $motion = "zoompan=z='min(zoom+0.0027,1.16)':x='(iw-iw/zoom)*on/60':y='ih/2-(ih/zoom/2)':d=1:s=1280x720:fps=30"
        }
        2 {
            $motion = "zoompan=z='if(eq(on,0),1.16,max(zoom-0.0028,1.01))':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1280x720:fps=30"
        }
        3 {
            $motion = "zoompan=z='min(zoom+0.0045,1.42)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1280x720:fps=30"
        }
        default {
            $motion = "zoompan=z='min(zoom+0.0024,1.15)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1280x720:fps=30"
        }
    }
    $filters += "[$index`:v]$motion,trim=duration=$duration,setpts=PTS-STARTPTS,format=yuv420p[v$index]"
}

$transitions = @(
    'fadewhite', 'smoothleft', 'wipeup', 'circleopen', 'dissolve', 'smoothright',
    'pixelize', 'wipeleft', 'circleclose', 'smoothup', 'fadeblack', 'wiperight',
    'slidedown', 'radial', 'fade', 'slideleft', 'zoomin', 'fadewhite'
)
$transitionDuration = 0.11
$previous = 'v0'
$timeline = [double]$scenes[0].Duration

for ($index = 1; $index -lt $preparedFrames.Count; $index++) {
    $output = "x$index"
    $offset = Format-Decimal ($timeline - $transitionDuration)
    $transition = $transitions[$index - 1]
    $filters += "[$previous][v$index]xfade=transition=$transition`:duration=0.11`:offset=$offset[$output]"
    $previous = $output
    $timeline += [double]$scenes[$index].Duration - $transitionDuration
}

$audioInput = $preparedFrames.Count
$filters += "[$previous]trim=duration=30,setpts=PTS-STARTPTS,vignette=PI/6,eq=contrast=1.05:saturation=1.08,unsharp=5`:5`:0.35,format=yuv420p[outv]"
$filters += "[$audioInput`:a]atrim=duration=30,asetpts=PTS-STARTPTS,afade=t=out`:st=29.25`:d=0.75,loudnorm=I=-13.5`:LRA=8`:TP=-1.2[outa]"
$filterComplex = $filters -join ';'

$ffmpegArguments += @(
    '-filter_complex', $filterComplex,
    '-map', '[outv]',
    '-map', '[outa]',
    '-t', '30',
    '-c:v', 'libx264',
    '-preset', 'medium',
    '-crf', '20',
    '-maxrate', '2200k',
    '-bufsize', '4400k',
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
    throw 'Der schnellere TV-Spot konnte nicht gerendert werden.'
}

& $FfmpegPath -hide_banner -loglevel error -y `
    -ss 28.55 -i $videoFile `
    -frames:v 1 -vf 'scale=1600:900:flags=lanczos' -c:v libwebp -quality 90 $posterFile

if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $posterFile -PathType Leaf)) {
    throw 'Das Poster der neuen Fassung konnte nicht erstellt werden.'
}

$previewTimes = @(2.1, 13.5, 17.7, 24.0, 29.0)
for ($index = 0; $index -lt $previewTimes.Count; $index++) {
    $preview = Join-Path $workDir ('preview-{0:D2}.png' -f ($index + 1))
    & $FfmpegPath -hide_banner -loglevel error -y `
        -ss $previewTimes[$index] -i $videoFile `
        -frames:v 1 -vf 'scale=1280:720:flags=lanczos' $preview
    if ($LASTEXITCODE -ne 0) {
        throw "Vorschau $($index + 1) konnte nicht erstellt werden."
    }
}

Write-Output "Video: $videoFile"
Write-Output "Poster: $posterFile"
Write-Output "Originaltrack: $musicFile"
Write-Output "Laufzeit vor finalem Trim: $(Format-Decimal $timeline) Sekunden"
