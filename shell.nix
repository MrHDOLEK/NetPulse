{
    php-version ? 8.5,
    with-xdebug ? false,
}:

let
    nixpkgs = fetchTarball {
        url = "https://github.com/NixOS/nixpkgs/archive/0c4e77908e1204498184d81cda8716e1ba4c47af.tar.gz";
        sha256 = "0mbr776gj2qk9klvara4zlww3g0da4nfbnrmi114nnmmayx3pyj4";
    };

    pkgs = import nixpkgs {
        config = {
            allowUnfree = true;
        };
    };

    base-php = if php-version == 8.4 then
        pkgs.php84
    else if php-version == 8.5 then
        pkgs.php85
    else if php-version == 8.3 then
        pkgs.php83
    else
        throw "Unknown php version ${toString php-version}";

    php = base-php.withExtensions (
        { enabled, all }:
        with all;
        enabled
            ++ [
                apcu
                bcmath
                ctype
                gd
                iconv
                intl
                mbstring
                pcntl
                pdo_pgsql
                pdo_sqlite
                sockets
                zip
            ]
            ++ (if with-xdebug then [ xdebug ] else [])
    );
in
pkgs.mkShell {
    buildInputs = [
        php
        php.packages.composer
        pkgs.symfony-cli
        pkgs.just
        pkgs.starship
    ];

    shellHook = ''
    if [ -f "$PWD/.nix/shell/starship.toml" ]; then
        export STARSHIP_CONFIG="$PWD/.nix/shell/starship.toml"
    else
        export STARSHIP_CONFIG="$PWD/.nix/shell/starship.toml.dist"
    fi

    eval "$(${pkgs.starship}/bin/starship init bash)"
    '';
}
