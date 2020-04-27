<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\User;
use App\Alias;
use App\Server;
use App\Application;
use Helper;


class AliasesController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {
        $user = User::find(Auth::id());
        $profile = $user->name;

        $aliases = Alias::orderBy('domain')->orderBy('application_id')->orderBy('server_id')->get();

        return view('aliases', compact('profile', 'aliases'));
    }


    public function create(Request $request)
    {


        $user = User::find(Auth::id());
        $profile = $user->name;


        $this->validate($request, [
            'domain' => 'required', 'server_id' => 'required', 'application_id' => 'required',
        ]);


        if(Application::where('domain', $request->domain)->where('server_id', $request->server_id)->get()->first()) {
            $messagge = "This domain is already taken on this server.";
            return view('generic', compact('profile','messagge'));
        }


        if(Alias::where('domain', $request->domain)->where('server_id', $request->server_id)->get()->first()) {
            $messagge = "This domain is already taken on this server.";
            return view('generic', compact('profile','messagge'));
        }


        $server      = Server::where('id', $request->server_id)->where('complete', 2)->get()->first();
        $application = Application::where('id', $request->application_id)->get()->first();


        if(!$server) {
            return abort(403);
        }

        $aliascode = md5(uniqid().microtime().$request->name);

        $ssh = New \phpseclib\Net\SSH2($server->ip, $server->port);
        if(!$ssh->login($server->username, $server->password)) {
            $messagge = 'There was a problem with server connection. Try later!';
            return view('generic', compact('profile','messagge'));
        }

        $configFile = "{$application->username}-{$application->domain}";

        Storage::disk('local')->put('public/'.$configFile.'.conf', '');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '<VirtualHost *:80>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '    ServerName '.$application->domain);
        foreach ($application->aliases as $alias) {
           Storage::disk('local')->append('public/'.$configFile.'.conf', '    ServerAlias '.$alias->domain);
        }
        Storage::disk('local')->append('public/'.$configFile.'.conf', '    ServerAlias '.$request->domain);
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        ServerAdmin webmaster@localhost');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        DocumentRoot /home/'.$application->username.'/'.$application->domain.'/web/'.$application->basepath);
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        <Directory />');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Order allow,deny');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Options FollowSymLinks');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Allow from all');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                AllowOverRide All');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Require all granted');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                SetOutputFilter DEFLATE');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        </Directory>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        <Directory /home/'.$application->username.'/'.$application->domain.'/web/'.$application->basepath.'>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Order allow,deny');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Options FollowSymLinks');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Allow from all');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                AllowOverRide All');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Require all granted');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                SetOutputFilter DEFLATE');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        </Directory>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '</VirtualHost>');

        $ssh->setTimeout(60);
        $ssh->exec('echo '.$server->password.' | sudo -S unlink /etc/apache2/sites-available/'.$configFile.'.conf');
        $ssh->exec('echo '.$server->password.' | sudo -S wget '.env('APP_URL').'/storage/'.$configFile.'.conf  -O /etc/apache2/sites-available/'.$configFile.'.conf');
        $ssh->exec('echo '.$server->password.' | sudo -S a2ensite '.$configFile.'.conf');
        $ssh->exec('echo '.$server->password.' | sudo -S service apache2 reload');
        $ssh->exec('echo '.$server->password.' | sudo -S systemctl reload apache2');

        Storage::disk('local')->delete('public/'.$configFile.'.conf');

        Alias::create([
            'domain'          => $request->domain,
            'server_id'       => $request->server_id,
            'application_id'  => $request->application_id,
            'aliascode'       => md5(uniqid().microtime().$request->name),
        ]);


        return redirect()->route('aliases');

    }




    public function delete(Request $request)
    {


        $user = User::find(Auth::id());
        $profile = $user->name;


        $this->validate($request, [
            'aliascode' => 'required',
        ]);


        $alias = Alias::where('aliascode', $request->aliascode)->get()->first();


        if(!$alias) {
            return abort(403);
        }

        $alias->delete();

        $ssh = New \phpseclib\Net\SSH2($alias->server->ip, $alias->server->port);
        if(!$ssh->login($alias->server->username, $alias->server->password)) {
            $messagge = 'There was a problem with server connection. Try later!';
            return view('generic', compact('profile','messagge'));
        }

        $configFile = "{$alias->application->username}-{$alias->application->domain}";

        Storage::disk('local')->put('public/'.$configFile.'.conf', '');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '<VirtualHost *:80>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '    ServerName '.$alias->application->domain);
        foreach ($alias->application->aliases as $alias) {
           Storage::disk('local')->append('public/'.$configFile.'.conf', '    ServerAlias '.$alias->domain);
        }
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        ServerAdmin webmaster@localhost');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        DocumentRoot /home/'.$alias->application->username.'/'.$alias->application->domain.'/web/'.$alias->application->basepath);
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        <Directory />');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Order allow,deny');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Options FollowSymLinks');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Allow from all');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                AllowOverRide All');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Require all granted');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                SetOutputFilter DEFLATE');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        </Directory>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        <Directory /home/'.$alias->application->username.'/'.$alias->application->domain.'/web/'.$alias->application->basepath.'>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Order allow,deny');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Options FollowSymLinks');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Allow from all');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                AllowOverRide All');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                Require all granted');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '                SetOutputFilter DEFLATE');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '        </Directory>');
        Storage::disk('local')->append('public/'.$configFile.'.conf', '</VirtualHost>');

        $ssh->setTimeout(60);
        $ssh->exec('echo '.$alias->server->password.' | sudo -S unlink /etc/apache2/sites-available/'.$configFile.'.conf');
        $ssh->exec('echo '.$alias->server->password.' | sudo -S wget '.env('APP_URL').'/storage/'.$configFile.'.conf  -O /etc/apache2/sites-available/'.$configFile.'.conf');
        $ssh->exec('echo '.$alias->server->password.' | sudo -S a2ensite '.$configFile.'.conf');
        $ssh->exec('echo '.$alias->server->password.' | sudo -S unlink /etc/cron.d/certbot_renew_'.$alias->domain.'.crontab');
        $ssh->exec('echo '.$alias->server->password.' | sudo -S unlink /cipi/certbot_renew_'.$alias->domain.'.sh');
        $ssh->exec('echo '.$alias->server->password.' | sudo -S service apache2 reload');
        $ssh->exec('echo '.$alias->server->password.' | sudo -S systemctl reload apache2');

        Storage::disk('local')->delete('public/'.$configFile.'.conf');

        return redirect()->route('aliases');

    }
}
