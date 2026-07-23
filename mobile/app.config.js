const appJson = require('./app.json');

const googleMapsApiKey = process.env.EXPO_PUBLIC_GOOGLE_MAPS_API_KEY;

module.exports = {
  expo: {
    ...appJson.expo,
    ios: {
      ...appJson.expo.ios,
      config: googleMapsApiKey
        ? {
            googleMapsApiKey,
          }
        : appJson.expo.ios?.config,
    },
    android: {
      ...appJson.expo.android,
      config: {
        ...appJson.expo.android?.config,
        ...(googleMapsApiKey
          ? {
              googleMaps: {
                apiKey: googleMapsApiKey,
              },
            }
          : {}),
      },
    },
  },
};
