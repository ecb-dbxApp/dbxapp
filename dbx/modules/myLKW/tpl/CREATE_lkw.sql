CREATE TABLE lkw (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    /* =========================================================
       Systemfelder (dbx)
       ========================================================= */
    create_date     TEXT NOT NULL DEFAULT '',
    create_uid      TEXT NOT NULL DEFAULT '',
    update_date     TEXT NOT NULL DEFAULT '',
    update_uid      TEXT NOT NULL DEFAULT '',
    owner           TEXT NOT NULL DEFAULT '',
	

    /* =========================================================
       LKW-Stammdaten
       ========================================================= */
    DOMICILIO     TEXT NOT NULL DEFAULT '',
    TRACTOR       TEXT NOT NULL DEFAULT '',
    ITV_TRACT     TEXT NOT NULL DEFAULT '',
    TIPO          TEXT NOT NULL DEFAULT '',
    REMOLQUE      TEXT NOT NULL DEFAULT '',
    ITV_REMOL     TEXT NOT NULL DEFAULT '',
    CONDUCTOR     TEXT NOT NULL DEFAULT '',
    TELF          TEXT NOT NULL DEFAULT '',
    EMPRESA       TEXT NOT NULL DEFAULT '',
    EXT           TEXT NOT NULL DEFAULT '',
    MANT          TEXT NOT NULL DEFAULT '',
    EVENTOS       TEXT NOT NULL DEFAULT '',
    BUJES         TEXT NOT NULL DEFAULT '',
    VENCIMIENTO   TEXT NOT NULL DEFAULT '',
    ANOTACIONES   TEXT NOT NULL DEFAULT '',
    ODOMETRO      TEXT NOT NULL DEFAULT '',

    /* =========================================================
       Disposición – d0 
       ========================================================= */
    d0_origen_region   TEXT NOT NULL DEFAULT '',
    d0_origen_lugar    TEXT NOT NULL DEFAULT '',
    d0_carga_region    TEXT NOT NULL DEFAULT '',
    d0_carga_lugar     TEXT NOT NULL DEFAULT '',
    d0_observaciones   TEXT NOT NULL DEFAULT '',

    /* =========================================================
       Disposición – d1 
       ========================================================= */
    d1_origen_region   TEXT NOT NULL DEFAULT '',
    d1_origen_lugar    TEXT NOT NULL DEFAULT '',
    d1_carga_region    TEXT NOT NULL DEFAULT '',
    d1_carga_lugar     TEXT NOT NULL DEFAULT '',
    d1_observaciones   TEXT NOT NULL DEFAULT '',

    /* =========================================================
       Disposición – d2  
       ========================================================= */
    d2_origen_region   TEXT NOT NULL DEFAULT '',
    d2_origen_lugar    TEXT NOT NULL DEFAULT '',
    d2_carga_region    TEXT NOT NULL DEFAULT '',
    d2_carga_lugar     TEXT NOT NULL DEFAULT '',
    d2_observaciones   TEXT NOT NULL DEFAULT '',


    /* =========================================================
       Disposición – d3  
       ========================================================= */
    d3_origen_region   TEXT NOT NULL DEFAULT '',
    d3_origen_lugar    TEXT NOT NULL DEFAULT '',
    d3_carga_region    TEXT NOT NULL DEFAULT '',
    d3_carga_lugar     TEXT NOT NULL DEFAULT '',
    d3_observaciones   TEXT NOT NULL DEFAULT '',

    /* =========================================================
       Disposición – d4  
       ========================================================= */
    d4_origen_region   TEXT NOT NULL DEFAULT '',
    d4_origen_lugar    TEXT NOT NULL DEFAULT '',
    d4_carga_region    TEXT NOT NULL DEFAULT '',
    d4_carga_lugar     TEXT NOT NULL DEFAULT '',
    d4_observaciones   TEXT NOT NULL DEFAULT '',

    /* =========================================================
       Disposición – d5 
       ========================================================= */
    d5_origen_region   TEXT NOT NULL DEFAULT '',
    d5_origen_lugar    TEXT NOT NULL DEFAULT '',
    d5_carga_region    TEXT NOT NULL DEFAULT '',
    d5_carga_lugar     TEXT NOT NULL DEFAULT '',
    d5_observaciones   TEXT NOT NULL DEFAULT ''    


)


CREATE INDEX idx_vehicle_tipo_remolque
ON vehicle (TIPO, REMOLQUE);