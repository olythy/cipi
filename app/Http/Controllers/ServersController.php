<?php

namespace App\Http\Controllers;

use App\Server;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ServersController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {
        $user = User::find(Auth::id());
        $profile = $user->name;

        $servers = Server::all();

        return view('servers', compact('profile', 'servers'));
    }


    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => 'required', 'ip' => 'required'
        ]);

        if($request->ip == $request->server('SERVER_ADDR')) {
            $user = User::find(Auth::id());
            $profile = $user->name;
            $messagge = "You can't install a client server into the same Cipi Server";
            return view('generic', compact('profile','messagge'));
            die();
        }

        Server::create([
            'name'      => $request->name,
            'provider'  => $request->provider,
            'location'  => $request->location,
            'ip'        => $request->ip,
            'port'      => 22,
            'username'  => 'cipi',
            'password'  => Str::random(64),
            'dbroot'    => uniqid().hash('crc32', Str::random(64)),
            'servercode'=> md5(uniqid().microtime().$request->name),
        ]);

        return redirect()->route('servers');
    }


    public function changeip(Request $request)
    {

        $this->validate($request, [
            'servercode' => 'required',
            'ip'         => 'required'
        ]);

        $server = Server::where('servercode', $request->servercode)->get()->first();
        $server->ip = $request->input('ip');
        $server->save();

        return redirect()->route('servers');

    }


    public function delete(Request $request)
    {

        $this->validate($request, [
            'servercode' => 'required',
        ]);

        $server = Server::where('servercode', $request->servercode)->get()->first();

        $server->delete();

        return redirect()->route('servers');

    }


}
