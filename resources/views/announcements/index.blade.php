<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements · {{ config('app.name', 'Barangay Portal') }}</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#15253b;background:#f4f7fb;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.topbar{height:76px;padding:0 max(24px,calc((100vw - 1240px)/2));display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #e7edf5}.brand{display:flex;align-items:center;gap:12px;font-weight:800;letter-spacing:-.02em}.brand-mark{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:#e9f1ff;color:#175cd3;font-size:20px}.user{display:flex;align-items:center;gap:16px;color:#64748b;font-size:13px}.logout{border:1px solid #dce4ee;background:#fff;padding:9px 13px;border-radius:9px;font-weight:700;color:#344054;cursor:pointer}.wrap{max-width:1240px;margin:0 auto;padding:42px 24px 64px}.intro{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:28px}.kicker{margin:0 0 8px;color:#175cd3;text-transform:uppercase;font-weight:800;font-size:11px;letter-spacing:.14em}h1{margin:0;font-size:34px;letter-spacing:-.045em}.subtitle{margin:8px 0 0;color:#66758b}.layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(330px,.85fr);gap:22px;align-items:start}.panel{background:#fff;border:1px solid #e3eaf3;border-radius:18px;box-shadow:0 8px 28px #20395608}.panel-head{padding:20px 22px;border-bottom:1px solid #edf1f6;display:flex;align-items:center;justify-content:space-between}.panel-head h2{font-size:16px;margin:0;letter-spacing:-.02em}.count{padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#526176;font-size:12px;font-weight:700}.list{padding:8px 22px}.item{padding:18px 0;border-bottom:1px solid #edf1f6}.item:last-child{border-bottom:0}.item-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.item h3{margin:0;font-size:16px;letter-spacing:-.02em}.meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:8px}.tag{padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#526176;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.tag.published{background:#e8f7ef;color:#17834b}.tag.draft{background:#fff5dc;color:#a15c00}.tag.archived{background:#f1f2f4;color:#667085}.tag.urgent,.tag.critical{background:#fff0ed;color:#c4320a}.preview{margin:11px 0 0;color:#5c6b80;font-size:13px;line-height:1.55;white-space:pre-line}.item-actions{display:flex;gap:8px;margin-top:13px}.small-btn{padding:7px 10px;border:1px solid #dce4ee;background:#fff;border-radius:8px;color:#344054;font-size:12px;font-weight:700;cursor:pointer}.small-btn.danger{color:#b42318;border-color:#f2d5d2}.form{padding:22px}.field{margin-bottom:16px}label{display:block;margin-bottom:7px;font-size:12px;font-weight:750;color:#344054}input,select,textarea{width:100%;padding:11px 12px;border:1px solid #d9e2ee;border-radius:9px;background:#fbfdff;color:#15253b;font:inherit;font-size:13px;outline:none}input:focus,select:focus,textarea:focus{border-color:#4383ef;box-shadow:0 0 0 3px #2563eb16}textarea{min-height:130px;resize:vertical;line-height:1.55}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.actions{display:flex;gap:9px;margin-top:20px}.primary{flex:1;border:0;border-radius:9px;padding:12px;background:#175cd3;color:white;font-weight:800;cursor:pointer}.primary:hover{background:#1849a9}.secondary{border:1px solid #dce4ee;border-radius:9px;padding:12px 15px;background:#fff;font-weight:700;color:#344054;cursor:pointer}.notice{margin-bottom:20px;padding:13px 16px;border-radius:11px;background:#e8f7ef;color:#147a45;font-size:13px;font-weight:700}.errors{margin-bottom:18px;padding:12px 16px;border-radius:10px;background:#fff0ed;color:#b42318;font-size:13px}.empty{padding:44px 22px;text-align:center;color:#66758b}.empty strong{display:block;margin-bottom:6px;color:#344054}.recipients{display:flex;align-items:center;gap:8px;color:#66758b;font-size:11px;margin-top:5px}.dot{width:7px;height:7px;border-radius:50%;background:#12b76a}@media(max-width:850px){.layout{grid-template-columns:1fr}.intro{align-items:flex-start;flex-direction:column}.topbar{padding:0 18px}.user span{display:none}}@media(max-width:480px){.wrap{padding:28px 14px}.grid{grid-template-columns:1fr}.topbar{height:66px}.brand{font-size:13px}}
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand"><div class="brand-mark" aria-hidden="true">✦</div><span>Barangay Services Portal</span></div>
        <div class="user"><span>{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sign out</button></form></div>
    </header>
    <main class="wrap">
        <div class="intro">
            <div><p class="kicker">Community communications</p><h1>Announcements</h1><p class="subtitle">Write an update once. Publishing notifies residents and staff in the portal and by email.</p></div>
        </div>
        @if (session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="errors"><strong>Please check the form:</strong><ul style="margin:7px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="layout">
            <section class="panel">
                <div class="panel-head"><h2>Recent announcements</h2><span class="count">{{ count($announcements) }} total</span></div>
                @forelse ($announcements as $announcement)
                    <article class="item">
                        <div class="item-top"><div><h3>{{ $announcement->title }}</h3><div class="meta"><span class="tag {{ $announcement->status }}">{{ $announcement->status }}</span><span class="tag">{{ str_replace('_', ' ', $announcement->type) }}</span><span class="tag {{ $announcement->severity }}">{{ $announcement->severity }}</span></div></div></div>
                        @if ($announcement->location)<div class="recipients">Location: {{ $announcement->location }}</div>@endif
                        <p class="preview">{{ \Illuminate\Support\Str::limit($announcement->body, 240) }}</p>
                        <div class="recipients"><span class="dot"></span>{{ $announcement->status === 'published' ? 'Published '.optional($announcement->published_at)->format('M j, Y · g:i A') : 'Not sent to recipients until published' }}</div>
                        <div class="item-actions">
                            <button class="small-btn" type="button" onclick="editAnnouncement({{ Illuminate\Support\Js::from($announcement->only(['id','title','body','type','severity','status','location','starts_at','ends_at'])) }})">Edit</button>
                            <form method="post" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?')">@csrf @method('DELETE')<button class="small-btn danger" type="submit">Delete</button></form>
                        </div>
                    </article>
                @empty
                    <div class="empty"><strong>No announcements yet</strong>Create a draft or publish your first community update.</div>
                @endforelse
            </section>
            <section class="panel" id="editor">
                <div class="panel-head"><div><h2 id="form-title">Create announcement</h2><div class="recipients"><span class="dot"></span>Published posts email residents and staff</div></div></div>
                <form class="form" id="announcement-form" method="post" action="{{ route('announcements.store') }}">
                    @csrf
                    <div id="method-field"></div>
                    <div class="field"><label for="title">Announcement title</label><input id="title" name="title" maxlength="255" value="{{ old('title') }}" placeholder="e.g. Community meeting this Friday" required></div>
                    <div class="field"><label for="body">Message</label><textarea id="body" name="body" maxlength="30000" placeholder="Share the details residents and staff need to know..." required>{{ old('body') }}</textarea></div>
                    <div class="grid">
                        <div class="field"><label for="type">Announcement type</label><select id="type" name="type" required><option value="announcement">Announcement</option><option value="advisory">Advisory</option><option value="emergency">Emergency</option><option value="evacuation_protocol">Evacuation protocol</option></select></div>
                        <div class="field"><label for="severity">Severity</label><select id="severity" name="severity" required><option value="info">Info</option><option value="warning">Warning</option><option value="urgent">Urgent</option><option value="critical">Critical</option></select></div>
                    </div>
                    <div class="field"><label for="location">Location <span style="font-weight:400;color:#98a2b3">(optional)</span></label><input id="location" name="location" maxlength="255" placeholder="Purok, street, or barangay-wide"></div>
                    <div class="grid">
                        <div class="field"><label for="starts_at">Starts at <span style="font-weight:400;color:#98a2b3">(optional)</span></label><input id="starts_at" name="starts_at" type="datetime-local"></div>
                        <div class="field"><label for="ends_at">Ends at <span style="font-weight:400;color:#98a2b3">(optional)</span></label><input id="ends_at" name="ends_at" type="datetime-local"></div>
                    </div>
                    <div class="field"><label for="status">Save as</label><select id="status" name="status" required><option value="draft">Draft — don't send yet</option><option value="published">Publish and notify everyone</option><option value="archived">Archived</option></select></div>
                    <div class="actions"><button class="primary" type="submit" id="submit-label">Save announcement</button><button class="secondary" type="button" id="cancel-edit" hidden onclick="resetAnnouncementForm()">Cancel edit</button></div>
                </form>
            </section>
        </div>
    </main>
    <script>
        const baseAction = @json(route('announcements.store'));
        const form = document.getElementById('announcement-form');
        const fields = ['title', 'body', 'type', 'severity', 'status', 'location', 'starts_at', 'ends_at'];
        function editAnnouncement(item) {
            form.action = `${baseAction}/${item.id}`;
            document.getElementById('method-field').innerHTML = '<input type="hidden" name="_method" value="PUT">';
            document.getElementById('form-title').textContent = 'Edit announcement';
            document.getElementById('submit-label').textContent = 'Update announcement';
            document.getElementById('cancel-edit').hidden = false;
            fields.forEach((name) => {
                const value = item[name] ?? '';
                document.getElementById(name).value = name.endsWith('_at') && value ? value.slice(0, 16) : value;
            });
            document.getElementById('editor').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function resetAnnouncementForm() {
            form.action = baseAction;
            document.getElementById('method-field').innerHTML = '';
            form.reset();
            document.getElementById('form-title').textContent = 'Create announcement';
            document.getElementById('submit-label').textContent = 'Save announcement';
            document.getElementById('cancel-edit').hidden = true;
        }
    </script>
</body>
</html>
