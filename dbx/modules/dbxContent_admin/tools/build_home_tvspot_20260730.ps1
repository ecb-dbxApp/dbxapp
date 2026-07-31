param(
    [Parameter(Mandatory = $true)]
    [string]$MusicFile,

    [string]$FfmpegPath = ''
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

if (-not (Test-Path -LiteralPath $FfmpegPath -PathType Leaf)) {
    throw "FFmpeg wurde nicht gefunden: $FfmpegPath"
}

$resolvedMusic = (Resolve-Path -LiteralPath $MusicFile).Path
$workDir = Join-Path $repoRoot 'files\tmp\dbxapp-tvspot-20260730'
$videoDir = Join-Path $repoRoot 'files\media\video'
$posterDir = Join-Path $repoRoot 'files\media\img\images'
$videoFile = Join-Path $videoDir 'dbxapp-tvspot-20260730.mp4'
$posterFile = Join-Path $posterDir 'dbxapp-tvspot-poster-20260730.webp'

New-Item -ItemType Directory -Force -Path $workDir, $videoDir, $posterDir | Out-Null

$fontBold = 'C\:/Windows/Fonts/arialbd.ttf'
$fontRegular = 'C\:/Windows/Fonts/arial.ttf'

$scenes = @(
    @{ File = 'files\media\img\images\dbxapp-eine-plattform-alle-moeglichkeiten.webp'; Title = '' },
    @{ File = 'files\media\img\hero\dbxapp-info-cms.webp'; Title = 'CMS' },
    @{ File = 'files\media\img\gallery\tutorial-cms-uebersicht.webp'; Title = 'INHALTE. STRUKTURIERT.' },
    @{ File = 'files\media\img\hero\dbxapp-info-shop.webp'; Title = 'SHOP' },
    @{ File = 'files\media\img\gallery\tutorial-shop-frontend-01-katalog.webp'; Title = 'VERKAUF. VERBUNDEN.' },
    @{ File = 'files\media\img\hero\dbxapp-info-ki.webp'; Title = 'KI' },
    @{ File = 'files\media\img\gallery\tutorial-workflow-nutzen-01-start.webp'; Title = 'ABLÄUFE. AUTOMATISIERT.' },
    @{ File = 'files\media\img\images\dbxapp-systeme-neu-denken-20260728.webp'; Title = '' },
    @{ File = 'files\media\img\gallery\tutorial-admin-dashboard-01-status-health.webp'; Title = 'SYSTEME. IM BLICK.' },
    @{ File = 'files\media\img\hero\dbxapp-desktop-mobile-cloud-hero.webp'; Title = 'WEB. DESKTOP. MOBIL.' },
    @{ File = 'files\media\img\images\dbxapp-modular-wachsen-20260728.webp'; Title = '' },
    @{ File = 'files\media\img\hero\dbxapp-platform-hero-20260728.webp'; Title = 'OFFEN. MODULAR. ERWEITERBAR.' }
)

$preparedFrames = @()
for ($index = 0; $index -lt $scenes.Count; $index++) {
    $scene = $scenes[$index]
    $source = Join-Path $repoRoot $scene.File
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw "Szenenbild fehlt: $source"
    }

    $target = Join-Path $workDir ('scene-{0:D2}.png' -f ($index + 1))
    $titleFilter = ''
    if ($scene.Title -ne '') {
        $safeTitle = $scene.Title.Replace("'", "\'")
        $titleFilter = ",drawtext=fontfile='$fontBold':text='$safeTitle':fontcolor=white:fontsize=46:x=70:y=610:box=1:boxcolor=0x061b3c@0.88:boxborderw=22"
    }

    $prepareFilter = "[0:v]split=2[bg][fg];" +
        "[bg]scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720," +
        "gblur=sigma=30,eq=brightness=-0.22:saturation=0.90[back];" +
        "[fg]scale=1280:720:force_original_aspect_ratio=decrease[front];" +
        "[back][front]overlay=(W-w)/2:(H-h)/2," +
        "drawbox=x=0:y=0:w=iw:h=ih:color=0x03152f@0.08:t=fill" +
        $titleFilter +
        ",format=rgb24[out]"

    & $FfmpegPath -hide_banner -loglevel error -y `
        -i $source `
        -filter_complex $prepareFilter `
        -map '[out]' -frames:v 1 $target

    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $target)) {
        throw "Szenenbild konnte nicht vorbereitet werden: $source"
    }
    $preparedFrames += $target
}

$endCard = Join-Path $workDir 'scene-13-endcard.png'
$endCardFilter = @(
    "drawgrid=width=80:height=80:thickness=1:color=0x38bdf8@0.08"
    "drawbox=x=70:y=80:w=1140:h=560:color=0x0b2d5c@0.72:t=fill"
    "drawbox=x=70:y=80:w=12:h=560:color=0x22d3ee@0.95:t=fill"
    "drawtext=fontfile='$fontBold':text='db':fontcolor=white:fontsize=118:x=360:y=155"
    "drawtext=fontfile='$fontBold':text='X':fontcolor=0xe62b2b:fontsize=118:x=493:y=155"
    "drawtext=fontfile='$fontBold':text='app':fontcolor=white:fontsize=118:x=575:y=155"
    "drawtext=fontfile='$fontBold':text='EINE PLATTFORM. ALLE MÖGLICHKEITEN.':fontcolor=0xdbeafe:fontsize=37:x=(w-text_w)/2:y=365"
    "drawtext=fontfile='$fontRegular':text='CMS  ·  SHOP  ·  APPS  ·  WORKFLOWS  ·  KI':fontcolor=0x7dd3fc:fontsize=28:x=(w-text_w)/2:y=446"
    "drawtext=fontfile='$fontBold':text='dbxapp.de':fontcolor=white:fontsize=42:x=(w-text_w)/2:y=535"
) -join ','

& $FfmpegPath -hide_banner -loglevel error -y `
    -f lavfi -i 'color=c=0x061b3c:s=1280x720:d=1:r=30' `
    -vf $endCardFilter -frames:v 1 $endCard

if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $endCard)) {
    throw 'Die Endcard konnte nicht erstellt werden.'
}
$preparedFrames += $endCard

$ffmpegArguments = @('-hide_banner', '-loglevel', 'error', '-y')
for ($index = 0; $index -lt $preparedFrames.Count; $index++) {
    $duration = if ($index -eq ($preparedFrames.Count - 1)) { '3.0' } else { '2.5' }
    $ffmpegArguments += @('-loop', '1', '-framerate', '30', '-t', $duration, '-i', $preparedFrames[$index])
}
$ffmpegArguments += @('-ss', '60', '-t', '30', '-i', $resolvedMusic)

$filters = @()
for ($index = 0; $index -lt $preparedFrames.Count; $index++) {
    $duration = if ($index -eq ($preparedFrames.Count - 1)) { '3.0' } else { '2.5' }
    if (($index % 2) -eq 0) {
        $motion = "zoompan=z='min(zoom+0.0012,1.09)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1280x720:fps=30"
    } else {
        $motion = "zoompan=z='min(zoom+0.0010,1.08)':x='(iw-iw/zoom)*on/90':y='ih/2-(ih/zoom/2)':d=1:s=1280x720:fps=30"
    }
    $filters += "[$index`:v]$motion,trim=duration=$duration,setpts=PTS-STARTPTS,format=yuv420p[v$index]"
}

$transitions = @(
    'fadewhite', 'smoothleft', 'wipeup', 'circleopen',
    'dissolve', 'smoothright', 'pixelize', 'fadeblack',
    'wipeleft', 'circleclose', 'smoothup', 'fade'
)

$previous = 'v0'
for ($index = 1; $index -lt $preparedFrames.Count; $index++) {
    $output = "x$index"
    $offset = [string]::Format(
        [Globalization.CultureInfo]::InvariantCulture,
        '{0:0.00}',
        (2.25 * $index)
    )
    $transition = $transitions[$index - 1]
    $filters += "[$previous][v$index]xfade=transition=$transition`:duration=0.25`:offset=$offset[$output]"
    $previous = $output
}

$audioInput = $preparedFrames.Count
$filters += "[$previous]vignette=PI/5,eq=contrast=1.04:saturation=1.06,format=yuv420p[outv]"
$filters += "[$audioInput`:a]atrim=duration=30,asetpts=PTS-STARTPTS,afade=t=in`:st=0`:d=0.12,afade=t=out`:st=29.15`:d=0.85,loudnorm=I=-14`:LRA=9`:TP=-1.2[outa]"
$filterComplex = $filters -join ';'

$ffmpegArguments += @(
    '-filter_complex', $filterComplex,
    '-map', '[outv]',
    '-map', '[outa]',
    '-c:v', 'libx264',
    '-preset', 'medium',
    '-crf', '21',
    '-maxrate', '1800k',
    '-bufsize', '3600k',
    '-r', '30',
    '-pix_fmt', 'yuv420p',
    '-profile:v', 'high',
    '-level', '4.0',
    '-c:a', 'aac',
    '-b:a', '160k',
    '-ar', '48000',
    '-movflags', '+faststart',
    '-shortest',
    $videoFile
)

& $FfmpegPath @ffmpegArguments
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $videoFile)) {
    throw 'Der TV-Spot konnte nicht gerendert werden.'
}

& $FfmpegPath -hide_banner -loglevel error -y `
    -ss 28.6 -i $videoFile `
    -frames:v 1 -vf 'scale=1600:900:flags=lanczos' -c:v libwebp -quality 88 $posterFile

if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $posterFile)) {
    throw 'Das Poster konnte nicht erstellt werden.'
}

$previewTimes = @(0.4, 9.3, 18.3, 29.0)
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
Write-Output "Vorschauen: $workDir\preview-01.png bis preview-04.png"
