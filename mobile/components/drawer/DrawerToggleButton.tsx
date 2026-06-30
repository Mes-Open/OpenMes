import { FontAwesome } from "@expo/vector-icons";
import { DrawerActions, useNavigation } from "@react-navigation/native";
import { Pressable } from "react-native";

import Colors from "@/constants/Colors";
import { useColorScheme } from "@/components/useColorScheme";

export function DrawerToggleButton() {
  const navigation = useNavigation();
  const scheme = useColorScheme();
  const palette = Colors[scheme];

  return (
    <Pressable
      onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
      hitSlop={8}
      style={({ pressed }) => ({
        marginLeft: 12,
        width: 36,
        height: 36,
        alignItems: "center",
        justifyContent: "center",
        borderRadius: 10,
        borderWidth: 1,
        borderColor: palette.border,
        opacity: pressed ? 0.6 : 1,
      })}
    >
      <FontAwesome name="bars" size={16} color={palette.text} />
    </Pressable>
  );
}
