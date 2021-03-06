<?php

namespace app\controller;

use app\util\Constants;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use app\model\Player;
use app\model\Game;
use app\model\Song;

class GameController extends AbstractController
{

    public function allGames(Request $request, Response $response, $args){
        $data = Game::with('players')->limit(10)->get();
        return $this->json_success($response, 200, json_encode($data));
    }

    public function play(Request $request, Response $response, $args)
    {
    	try 
    	{
    		$data = $request->getParsedBody();

    		if (!isset($data["gameName"]) || !isset($data["userName"]) || !isset($data["owner"]) || !isset($data["songs"]) || !isset($data['categoryId']))
    			return $this->json_error($response, 400, "Missing parameters");

    		$player = Player::where("spotify_id", "=", filter_var($data['owner'], FILTER_SANITIZE_STRING))->first();
            if (is_null($player)) {
                $player = new Player();
        		$player->spotify_id = filter_var($data['owner'], FILTER_SANITIZE_STRING);
        		$player->pseudo = filter_var($data['userName'], FILTER_SANITIZE_STRING);
        		$player->save();
            }
            $game = new Game();
            $game->name = filter_var($data['gameName'], FILTER_SANITIZE_STRING);
            $game->owner = $player->spotify_id;
            $game->state = Constants::GAME_STARTED_SINGLE_PLAYER;
            $game->category = filter_var($data['categoryId'], FILTER_SANITIZE_STRING);
            $game->save();

    		foreach ($data["songs"] as $song) {
    		    $newSong = Song::find($song['id']);
    		    if(is_null($newSong)){
                    $newSong = new Song();
                    $newSong->spotify_id = filter_var($song['id'], FILTER_SANITIZE_STRING);
                    $newSong->name = filter_var($song['name'], FILTER_SANITIZE_STRING);
                    $newSong->url = filter_var($song['uri'], FILTER_SANITIZE_STRING);
                    $newSong->preview_url = filter_var($song['preview_url'], FILTER_SANITIZE_STRING);
                    $newSong->save();
                }
                $newSong->games()->attach($game);
    			$newSong->save();
    		}
            $game->players()->attach($player->id, ['score'=> 5]);
    		$game->save();
            $tab = ["id"=>$game->id, "name"=>$game->name, "state"=>$game->state, "score"=>$game->score, "player"=>$player, "songs"=>$game->songs];
    		return $this->json_success($response, 201, json_encode($tab));

    	} catch (ModelNotFoundException $mne) {
    		return $this->json_error($response, 404, "Not found");
    	}catch (\Exception $e){
    	    return $this->json_error($response, 400, $e->getMessage());
        }
    }

    public function joinGame(Request $request, Response $response, $args)
    {
        try
        {
            $gameId = filter_var($args['id']);
            $data = $request->getParsedBody();
            $player = Player::where("spotify_id", "=", filter_var($data['spotify_id'], FILTER_SANITIZE_STRING))->first();
            if (is_null($player)) {
                $player = new Player();
                $player->spotify_id = filter_var($data['spotify_id'], FILTER_SANITIZE_STRING);
                $player->pseudo = filter_var($data['userName'], FILTER_SANITIZE_STRING);
                $player->save();
            }
            $game = Game::where("id", "=", $gameId)->firstOrFail();
            $game->players()->attach($player->id, ['score'=>0]);
            $game->save();
            $tab = ["game"=>$game, "players"=>$game->players, "songs"=>$game->songs];
            return $this->json_success($response, 200, json_encode($tab));

        } catch (ModelNotFoundException $mne) {
            return $this->json_error($response, 404, "Not found");
        }catch (\Exception $e){
            return $this->json_error($response, 400, $e->getMessage());
        }

    }

    public function finish($request, $response, $args)
    {
        $data = $request->getParsedBody();
        try
        {
            $game = Game::where('id', '=', filter_var($data['game'], FILTER_SANITIZE_STRING))->firstOrfail();
            switch ($game->state) {
                case Constants::GAME_STARTED_SINGLE_PLAYER :
                    $game->state = Constants::GAME_END_SINGLE_PLAYER;
                    break;
                case Constants::GAME_STARTED_OTHER_PLAYER :
                    $game->state = Constants::GAME_END_OTHER_PLAYER;
                    break;
                default :
                    return $this->json_error($response, 403, "State not valid");
            }

            $player = Player::where('spotify_id', '=', filter_var($data['player'], FILTER_SANITIZE_STRING))->firstOrfail();
            $game->players()->updateExistingPivot($player->id, array('score' => $data['score']), true);
            $game->players()->updateExistingPivot($player->id, array('duration' => $data['duration']), true);
            $game->save();
            return $this->json_success($response, 200, json_encode($data));
        }
        catch (ModelNotFoundException $mne) {
            return $this->json_error($response, 404, "Not found");
        }
    }

    public function rankings($request, $response, $args)
    {
        $data = $request->getParsedBody();
        try
        {
            $games = Game::limit(30)->get();
            foreach($games as $game)
            {
                foreach($game->players as $player)
                {   
                    $array[] = array(
                        'Pseudo' => $player->pseudo,
                        'Score' => $player->pivot->score,
                        'Duration' => $player->pivot->duration
                    );
                }
            }
            
            return $this->json_success($response, 200, json_encode($array));
        }
        catch (ModelNotFoundException $mne) {
            return $this->json_error($response, 404, "Not found");
        }
    }
}