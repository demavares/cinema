<?php
// ============================================
// HELPERS - TMDb
// ============================================
function getMovieFromTMDB($title, $year = null)
{
    $api_key = TMDB_API_KEY;

    if (empty($api_key)) {
        error_log("⚠️ TMDB_API_KEY no configurada");
        return null;
    }

    $query = urlencode($title);
    $url = TMDB_API_URL . "search/movie?api_key={$api_key}&query={$query}&language=es-ES";

    if ($year) {
        $url .= "&year={$year}";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, TMDB_TIMEOUT);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Error en API TMDB: HTTP $httpCode");
        return null;
    }

    $data = json_decode($response, true);

    if (!empty($data['results'])) {
        $movie_data = $data['results'][0];
        $tmdb_id = $movie_data['id'];

        $detail_url = TMDB_API_URL . "movie/{$tmdb_id}?api_key={$api_key}&language=es-ES&append_to_response=credits";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $detail_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, TMDB_TIMEOUT);

        $detail_response = curl_exec($ch);
        curl_close($ch);

        $detail_data = json_decode($detail_response, true);

        $genres = [];
        if (isset($detail_data['genres'])) {
            foreach ($detail_data['genres'] as $genre) {
                $genres[] = $genre['name'];
            }
        }

        $directors = [];
        if (isset($detail_data['credits']['crew'])) {
            foreach ($detail_data['credits']['crew'] as $crew) {
                if ($crew['job'] === 'Director') {
                    $directors[] = $crew['name'];
                }
            }
        }

        $director = !empty($directors) ? implode(' y ', $directors) : 'No disponible';

        $cast = [];
        if (isset($detail_data['credits']['cast'])) {
            $top_cast = array_slice($detail_data['credits']['cast'], 0, 6);
            foreach ($top_cast as $actor) {
                $cast[] = $actor['name'];
            }
        }

        $cast_members = !empty($cast) ? implode(', ', $cast) : '';

        $country = '';
        if (isset($detail_data['production_countries']) && !empty($detail_data['production_countries'])) {
            $english_country = $detail_data['production_countries'][0]['name'];

            $country_translations = [
                'United States of America' => 'Estados Unidos de América',
                'United States' => 'Estados Unidos de América',
                'Japan' => 'Japón',
                'United Kingdom' => 'Reino Unido',
                'France' => 'Francia',
                'Germany' => 'Alemania',
                'South Korea' => 'Corea del Sur',
                'China' => 'China',
                'Canada' => 'Canadá',
                'Spain' => 'España',
                'Italy' => 'Italia',
                'Mexico' => 'México',
                'India' => 'India',
                'Australia' => 'Australia',
                'Venezuela' => 'Venezuela',
                'Argentina' => 'Argentina',
                'Colombia' => 'Colombia',
                'Chile' => 'Chile',
                'Peru' => 'Perú',
                'Brazil' => 'Brasil'
            ];

            $country = $country_translations[$english_country] ?? $english_country;
        }

        return [
            'tmdb_id' => $tmdb_id,
            'description' => $movie_data['overview'] ?? '',
            'poster_path' => $movie_data['poster_path'] ?? null,
            'backdrop_path' => $movie_data['backdrop_path'] ?? null,
            'vote_average' => $movie_data['vote_average'] ?? 0,
            'genres' => implode(', ', $genres),
            'director' => $director,
            'cast_members' => $cast_members,
            'country' => $country,
            'runtime' => $detail_data['runtime'] ?? 0,
            'release_date' => $movie_data['release_date'] ?? '',
            'year' => !empty($movie_data['release_date']) ? date('Y', strtotime($movie_data['release_date'])) : null
        ];
    }

    return null;
}