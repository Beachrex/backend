-- PostGIS initialization script for BeachRex VPS (multi-database)
-- Production/uat/dev databases live in a single PostgreSQL container.
-- This file runs only on FIRST container start (docker-entrypoint-initdb.d).
-- It creates the two extra databases (prod DB is created via POSTGRES_DB)
-- and enables PostGIS + spatial helper functions in each.

-- 1. Create the extra databases (beachrex_prod is created by POSTGRES_DB env)
CREATE DATABASE beachrex_uat;
CREATE DATABASE beachrex_dev;

-- 2. Enable PostGIS + topology in the primary (POSTGRES_DB) database
\connect beachrex_prod
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
SELECT PostGIS_Version();

-- 3. Enable PostGIS + topology in uat
\connect beachrex_uat
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
SELECT PostGIS_Version();

-- 4. Enable PostGIS + topology in dev
\connect beachrex_dev
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
SELECT PostGIS_Version();

-- 5. Spatial helper functions (created in each database separately)
--    Mirrors database/postgis/init-postgis.sql

\connect beachrex_prod
CREATE OR REPLACE FUNCTION calculate_distance_km(
    lat1 DOUBLE PRECISION,
    lon1 DOUBLE PRECISION,
    lat2 DOUBLE PRECISION,
    lon2 DOUBLE PRECISION
) RETURNS DOUBLE PRECISION AS $$
BEGIN
    RETURN ST_Distance_Sphere(
        ST_MakePoint(lon1, lat1),
        ST_MakePoint(lon2, lat2)
    ) / 1000.0;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION beaches_within_radius(
    center_lat DOUBLE PRECISION,
    center_lon DOUBLE PRECISION,
    radius_km DOUBLE PRECISION
) RETURNS TABLE(
    beach_id BIGINT,
    distance_km DOUBLE PRECISION
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        b.id,
        calculate_distance_km(center_lat, center_lon, b.latitude::DOUBLE PRECISION, b.longitude::DOUBLE PRECISION) as distance
    FROM beach b
    WHERE b.latitude IS NOT NULL
      AND b.longitude IS NOT NULL
      AND calculate_distance_km(center_lat, center_lon, b.latitude::DOUBLE PRECISION, b.longitude::DOUBLE PRECISION) <= radius_km
    ORDER BY distance;
END;
$$ LANGUAGE plpgsql;

\connect beachrex_uat
CREATE OR REPLACE FUNCTION calculate_distance_km(
    lat1 DOUBLE PRECISION,
    lon1 DOUBLE PRECISION,
    lat2 DOUBLE PRECISION,
    lon2 DOUBLE PRECISION
) RETURNS DOUBLE PRECISION AS $$
BEGIN
    RETURN ST_Distance_Sphere(
        ST_MakePoint(lon1, lat1),
        ST_MakePoint(lon2, lat2)
    ) / 1000.0;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION beaches_within_radius(
    center_lat DOUBLE PRECISION,
    center_lon DOUBLE PRECISION,
    radius_km DOUBLE PRECISION
) RETURNS TABLE(
    beach_id BIGINT,
    distance_km DOUBLE PRECISION
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        b.id,
        calculate_distance_km(center_lat, center_lon, b.latitude::DOUBLE PRECISION, b.longitude::DOUBLE PRECISION) as distance
    FROM beach b
    WHERE b.latitude IS NOT NULL
      AND b.longitude IS NOT NULL
      AND calculate_distance_km(center_lat, center_lon, b.latitude::DOUBLE PRECISION, b.longitude::DOUBLE PRECISION) <= radius_km
    ORDER BY distance;
END;
$$ LANGUAGE plpgsql;

\connect beachrex_dev
CREATE OR REPLACE FUNCTION calculate_distance_km(
    lat1 DOUBLE PRECISION,
    lon1 DOUBLE PRECISION,
    lat2 DOUBLE PRECISION,
    lon2 DOUBLE PRECISION
) RETURNS DOUBLE PRECISION AS $$
BEGIN
    RETURN ST_Distance_Sphere(
        ST_MakePoint(lon1, lat1),
        ST_MakePoint(lon2, lat2)
    ) / 1000.0;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION beaches_within_radius(
    center_lat DOUBLE PRECISION,
    center_lon DOUBLE PRECISION,
    radius_km DOUBLE PRECISION
) RETURNS TABLE(
    beach_id BIGINT,
    distance_km DOUBLE PRECISION
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        b.id,
        calculate_distance_km(center_lat, center_lon, b.latitude::DOUBLE PRECISION, b.longitude::DOUBLE PRECISION) as distance
    FROM beach b
    WHERE b.latitude IS NOT NULL
      AND b.longitude IS NOT NULL
      AND calculate_distance_km(center_lat, center_lon, b.latitude::DOUBLE PRECISION, b.longitude::DOUBLE PRECISION) <= radius_km
    ORDER BY distance;
END;
$$ LANGUAGE plpgsql;
