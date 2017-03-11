angular.module('spotyGame').controller('gameController', ["$scope" ,"Spotify", "$sce", "GameFactory", "Song", function ($scope, Spotify, $sce, GameFactory, Song) {

    $scope.position = 0;
    $scope.songs= [];
    $scope.choices= [];
    $scope.isPlaying = false;
    $scope.score = 0;

    var getSongs = function(category) {
        $getRandomOffset = Math.floor((Math.random() * 1000) + 1);
        $options = {
            'limit' : '10',
            'offset' : $getRandomOffset
        };
        Spotify.search('genre:'+category, 'track', $options).then(function (response) {
            $scope.songs = response.data.tracks.items;
            getChoices('pop');
        });
    };

    var loadNewSong = function()
    {
        $scope.song = $scope.songs[$scope.position];
        console.log($scope.song);
        $scope.playUri = $sce.trustAsResourceUrl("https://embed.spotify.com/?uri=spotify%3Atrack%3A"+$scope.song.id);
    }

    var createGame = function () {
        var songsToSend = [];
        $scope.songs.forEach(function (e) {
            songsToSend.push(new Song(e));
        });
        GameFactory.startGame({
            "gameName": "Jeu",
            "userName": $scope.currentUser.displayName || $scope.currentUser.id,
            "owner": $scope.currentUser.id,
            "songs": songsToSend
        }).then(function (response) {
            console.log(response.data);
            loadNewSong();
            $scope.playUri = $sce.trustAsResourceUrl("https://embed.spotify.com/?uri=spotify%3Atrack%3A"+$scope.song.id);
            $scope.isPlaying = true;
        }, function (error) {
            console.log(error);
        });
    };

    var initGame = function () {

        Spotify.getCurrentUser().then(function (response) {
            $scope.currentUser = response.data;
            $scope.currentUserUri = $sce.trustAsResourceUrl("https://embed.spotify.com/follow/1/?uri=spotify%3Auser%3A"+$scope.currentUser.id+"&size=detail&theme=dark");
            Spotify.getCategories({}).then(function (response) {
                $scope.categories = response.data.categories.items;
            });

        });
    };

    $scope.selectCategory = function (category) {
      getSongs(category.id);
    };

    var getChoices = function(category)
    {
        $getRandomOffset = Math.floor((Math.random() * 1000) + 1);
        $options = {
            'limit' : '3',
            'offset' : $getRandomOffset
        };
        Spotify.search('genre:'+category, 'track', $options).then(function (response) {
            $scope.choices = response.data.tracks.items;
            $scope.choices.push($scope.song);
            createGame();
        });
    };

    $scope.checkChoice = function(id)
    {
        $scope.position = $scope.position+1;
        if($scope.song.id == id)
        {
            $scope.score = $scope.score + 1;
        }
        loadNewSong();
    };

    initGame();
}]);