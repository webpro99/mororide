module.exports = function (api) {
  api.cache(true);

  return {
    presets: ['babel-preset-expo'],
    // Laravel Echo 2.x ships static class blocks. Expo SDK 52's Metro
    // transformer needs this explicit compatibility transform.
    plugins: ['@babel/plugin-transform-class-static-block'],
  };
};
