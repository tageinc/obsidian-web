<div style="margin: 0 auto;">
    <h3>SETTINGS</h3>
    @if (session()->has('message'))
    <div class="alert alert-success">
        {{ session('message') }}
    </div>
    @endif
    <table class="settings_table">
        <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
                <!--<th style="text-align: center;">Actions</th>-->
            </tr>
        </thead>
        <tbody>
            @forelse($config as $conf)
            <tr>
                <td><input class="form-control" type="text" value="{{$conf->key}}" disabled=""></td>
                <td><input wire:keydown.enter="saveConfig($event.target.name, $event.target.value)" class="form-control" type="text" name="{{$conf->key}}" value="{{$conf->value}}"></td>
            </tr>
            @empty
            <tr>
                <td colspan="3">No configs available.</td>
            </tr>
            @endforelse
            @forelse($software as $soft)
            <tr>
                <td colspan="2" style="text-align: center;"><b>---- {{$soft['name']}} ----</b></td>
            </tr>
            @foreach($soft as $key => $value)
            @if($key != 'id' && $key != 'key' && $key != 'created_at' && $key != 'updated_at' && $key != 'name' && $key != 'slug')
            @if($key == 'url')
            <tr>
                <td><input class="form-control" type="text" value="{{$key}}" disabled></td>
                <td><input class="form-control" type="text"value="{{$value}}" disabled></td>
            </tr>
            @else
            <tr>
                <td><input class="form-control" type="text" value="{{$key}}" disabled=""></td>
                <td><input wire:keydown.enter="saveSoftware($event.target.id, $event.target.name, $event.target.value)" class="form-control" type="text" name="{{$key}}" value="{{$value}}" id="{{$soft['id']}}"></td>
            </tr>
            @endif
            @endif
            @endforeach
            @empty
            <tr>
                <td colspan="3">No configs available.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="card p-2 mt-4">
        <h3>ClientConnect (client_connect_installer.exe)</h3>
        <form id="upload_form_clientconnect" onsubmit="event.preventDefault(); uploadFile('clientconnect');" enctype="multipart/form-data">
            <input class="form-control" type="file" name="software" accept=".exe">

            @error('hydronium') <span class="error">{{ $message }}</span> @enderror

            <button id="upload_btn_clientconnect" class="btn btn-primary" type="submit">Save</button>
        </form>
    </div>

    <div class="card p-2 mt-4">
        <h3>Hydronium (hydronium_installer.exe)</h3>
        <form id="upload_form_hydronium" onsubmit="event.preventDefault(); uploadFile('hydronium');" enctype="multipart/form-data">
            <input class="form-control" type="file" name="software" accept=".exe">

            @error('hydronium') <span class="error">{{ $message }}</span> @enderror

            <button id="upload_btn_hydronium" class="btn btn-primary" type="submit">Save</button>
        </form>
    </div>
</div>
<script>
    async function uploadFile(software) {

        let btn;
        btn = document.getElementById('upload_btn_' + software);
        btn.style.backgroundColor = "grey";
        btn.innerText = "Uploading...";
        btn.style.cursor = "not-allowed";
        btn.style.pointerEvents = "none";

        let data = new FormData(document.getElementById('upload_form_' + software));

        let response = await fetch('upload.php', {
            method: 'POST',
            body: data
        });

        if(response.ok) {
            let json = await response.json();
            console.log(json);
            Livewire.emit('showMessage', software +': uploaded!.');
        } else {
            console.log("error: " + response.status);
            Livewire.emit('showMessage', software +': can\'t be uploaded, please contact an admin!.');
        }

        btn.style.backgroundColor = "#3490dc";
        btn.innerText = "Save";
        btn.style.cursor = "pointer";
        btn.style.pointerEvents = "all";
    }
</script>
<style>
.settings_table {
    width: 100%;
}
</style>