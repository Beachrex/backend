-- PostGIS initialization script for BeachRex
-- This script enables PostGIS extension and creates necessary spatial functions

-- Enable PostGIS extension
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

-- Verify PostGIS installation
SELECT PostGIS_Version();

-- Create spatial reference systems if needed
-- EPSG:4326 (WGS84) is the standard for GPS coordinates and should already be available

-- Create helper functions for distance calculations
CREATE OR REPLACE FUNCTION calculate_distance_km(
    lat1 DOUBLE PRECISION,
    lon1 DOUBLE PRECISION,
    lat2 DOUBLE PRECISION,
    lon2 DOUBLE PRECISION
) RETURNS DOUBLE PRECISION AS $$
BEGIN
    -- Calculate distance using PostGIS ST_Distance_Sphere function
    -- Returns distance in meters, convert to kilometers
    RETURN ST_Distance_Sphere(
        ST_MakePoint(lon1, lat1),
        ST_MakePoint(lon2, lat2)
    ) / 1000.0;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Create function to find beaches within radius
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

-- Log successful initialization
INSERT INTO information_schema.sql_features (feature_id, feature_name, sub_feature_id, sub_feature_name, is_supported, comments)
VALUES ('POSTGIS_INIT', 'PostGIS Extension Initialized', '1', 'BeachRex Spatial Functions', 'YES', 'PostGIS successfully enabled for BeachRex application')
ON CONFLICT DO NOTHING;
